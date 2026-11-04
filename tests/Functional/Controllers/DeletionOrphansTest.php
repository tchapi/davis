<?php

namespace App\Tests\Functional;

use App\Entity\AddressBook;
use App\Entity\CalendarInstance;
use App\Entity\Principal;
use App\Entity\User;
use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The admin UI deletes rows directly in the database instead of over DAV, so it has to remove
 * everything sabre would have removed on `afterUnbind`. Nothing in the schema catches a mistake
 * here: `propertystorage` is keyed by path and has no foreign keys, so leftovers are silent.
 */
class DeletionOrphansTest extends WebTestCase
{
    use AdminPostTrait;

    private function userId($client, string $username): int
    {
        return static::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(User::class)->findOneByUsername($username)->getId();
    }

    private function em()
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function storeProperty(string $path, string $name = '{DAV:}displayname'): void
    {
        $em = $this->em();
        $em->getConnection()->executeStatement(
            'INSERT INTO propertystorage (path, name, valuetype, value) VALUES (?, ?, 1, ?)',
            [$path, $name, 'stored by a client']
        );
    }

    private function propertyPaths(): array
    {
        return $this->em()->getConnection()
            ->executeQuery('SELECT path FROM propertystorage')->fetchFirstColumn();
    }

    /**
     * sabre stores properties against the DAV path. If they are not removed with the account, the
     * next account with the same name picks them up.
     */
    public function testDeletingAUserRemovesItsStoredProperties(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $userId = $this->userId($client, 'test_user');

        $this->storeProperty('principals/test_user');
        $this->storeProperty('calendars/test_user/default');
        $this->storeProperty('calendars/test_user/default/event.ics');
        $this->storeProperty('addressbooks/test_user/default');
        // Another account's rows, to be sure the prefix match is not too greedy
        $this->storeProperty('calendars/test_user2/default');

        $this->postAdmin($client, '/users/delete/'.$userId);
        $this->assertResponseRedirects('/users/');

        $paths = $this->propertyPaths();
        $this->assertNotContains('principals/test_user', $paths);
        $this->assertNotContains('calendars/test_user/default', $paths);
        $this->assertNotContains('calendars/test_user/default/event.ics', $paths);
        $this->assertNotContains('addressbooks/test_user/default', $paths);
        $this->assertContains('calendars/test_user2/default', $paths, 'Another account must keep its properties');
    }

    /**
     * `_` is a wildcard in LIKE and usernames are allowed to contain one, so an unescaped prefix
     * would also match `testXuser`.
     */
    public function testTheUnderscoreInAUsernameIsNotTreatedAsAWildcard(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $userId = $this->userId($client, 'test_user');

        $this->storeProperty('calendars/test_user/default');
        $this->storeProperty('calendars/testXuser/default');

        $this->postAdmin($client, '/users/delete/'.$userId);

        $paths = $this->propertyPaths();
        $this->assertNotContains('calendars/test_user/default', $paths);
        $this->assertContains('calendars/testXuser/default', $paths);
    }

    /**
     * `groupmembers.member_id` has a foreign key with no cascade, so deleting an account somebody
     * delegated to would fail if its row stayed behind. Doctrine already removes it, because
     * `Principal::$delegees` points at Principal itself and Doctrine clears both columns of a
     * self-referencing join table. This test is here so we notice if that mapping changes.
     */
    public function testDeletingAUserThatIsSomeoneElsesDelegateLeavesNoGroupMembers(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $em = $this->em();
        $victimId = $this->userId($client, 'test_user2');

        // test_user delegates to test_user2, so test_user2 is the member of the row
        $ownerProxy = $em->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user'.Principal::WRITE_PROXY_SUFFIX);
        $delegate = $em->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user2');
        $this->assertNotNull($ownerProxy);
        $this->assertNotNull($delegate);

        $ownerProxy->addDelegee($delegate);
        $em->flush();
        $delegateId = $delegate->getId();

        $this->assertSame(1, (int) $em->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM groupmembers WHERE member_id = ?', [$delegateId])->fetchOne());

        $this->postAdmin($client, '/users/delete/'.$victimId);
        $this->assertResponseRedirects('/users/', null, 'Deleting a delegate must not blow up on the foreign key');

        $em = $this->em();
        $this->assertSame(0, (int) $em->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM groupmembers WHERE member_id = ?', [$delegateId])->fetchOne());
    }

    /**
     * Turning delegation off deletes the two proxy principals, so deleting such an account has to
     * cope with them being gone.
     */
    public function testDeletingAUserWithDelegationTurnedOff(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $userId = $this->userId($client, 'test_user');

        $this->postAdmin($client, '/users/delegation/'.$userId.'/off');
        $this->assertResponseRedirects();

        $em = $this->em();
        $this->assertNull($em->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user'.Principal::READ_PROXY_SUFFIX));

        $this->postAdmin($client, '/users/delete/'.$userId);
        $this->assertResponseRedirects('/users/');

        $em = $this->em();
        $this->assertNull($em->getRepository(User::class)->findOneByUsername('test_user'));
        $this->assertNull($em->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user'));
        $this->assertSame([], $em->getRepository(CalendarInstance::class)->findBy(['principalUri' => Principal::PREFIX.'test_user']));
        $this->assertSame([], $em->getRepository(AddressBook::class)->findBy(['principalUri' => Principal::PREFIX.'test_user']));
    }

    public function testDeletingACalendarRemovesItsStoredProperties(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $userId = $this->userId($client, 'test_user');
        $instance = $this->em()->getRepository(CalendarInstance::class)
            ->findOneBy(['principalUri' => Principal::PREFIX.'test_user', 'uri' => 'default']);

        $this->storeProperty('calendars/test_user/default');
        $this->storeProperty('calendars/test_user/default/event.ics');
        $this->storeProperty('calendars/test_user/other');

        $this->postAdmin($client, '/calendars/'.$userId.'/delete/'.$instance->getId());
        $this->assertResponseRedirects('/calendars/'.$userId);

        $paths = $this->propertyPaths();
        $this->assertNotContains('calendars/test_user/default', $paths);
        $this->assertNotContains('calendars/test_user/default/event.ics', $paths);
        $this->assertContains('calendars/test_user/other', $paths, 'Another calendar must keep its properties');
    }

    public function testDeletingAnAddressBookRemovesItsStoredProperties(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $userId = $this->userId($client, 'test_user');
        $addressbook = $this->em()->getRepository(AddressBook::class)
            ->findOneBy(['principalUri' => Principal::PREFIX.'test_user', 'uri' => 'default']);

        $this->storeProperty('addressbooks/test_user/default');
        $this->storeProperty('addressbooks/test_user/default/jane.vcf');
        $this->storeProperty('addressbooks/test_user/other');

        $this->postAdmin($client, '/addressbooks/'.$userId.'/delete/'.$addressbook->getId());
        $this->assertResponseRedirects('/addressbooks/'.$userId);

        $paths = $this->propertyPaths();
        $this->assertNotContains('addressbooks/test_user/default', $paths);
        $this->assertNotContains('addressbooks/test_user/default/jane.vcf', $paths);
        $this->assertContains('addressbooks/test_user/other', $paths);
    }
}
