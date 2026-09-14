<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sabre\DAV\PropertyStorage\Backend\PDO as PropertyStorageBackend;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PropertyStorageTest extends KernelTestCase
{
    private const PATH = 'calendars/test_user/default';
    private const COLOR = '{http://apple.com/ns/ical/}calendar-color';

    private EntityManagerInterface $em;
    private PropertyStorageBackend $backend;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->backend = new PropertyStorageBackend($this->em->getConnection()->getNativeConnection());

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function setProperty(string $name, ?string $value, string $path = self::PATH): void
    {
        $propPatch = new PropPatch([$name => $value]);
        $this->backend->propPatch($path, $propPatch);

        $this->assertTrue($propPatch->commit(), sprintf('Storing %s should succeed', $name));
    }

    private function getProperty(string $name, string $path = self::PATH)
    {
        $propFind = new PropFind($path, [$name]);
        $this->backend->propFind($path, $propFind);

        return $propFind->get($name);
    }

    private function countRows(string $path = self::PATH): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM propertystorage WHERE path = ?', [$path]);
    }

    /**
     * Regression test for issue #279: sabre/dav upserts dead properties on (path, name).
     * Without a unique index MySQL and SQLite appended a row on every PROPPATCH instead of
     * replacing, and PostgreSQL rejected the statement outright with SQLSTATE 42P10.
     */
    public function testRepeatedPropPatchReplacesInsteadOfAccumulating(): void
    {
        $this->setProperty(self::COLOR, '#FF0000');
        $this->setProperty(self::COLOR, '#00FF00');
        $this->setProperty(self::COLOR, '#0000FF');

        $this->assertSame(1, $this->countRows(), 'A property must be stored exactly once');
        $this->assertSame('#0000FF', $this->getProperty(self::COLOR));
    }

    public function testDifferentPropertiesOnTheSamePathCoexist(): void
    {
        $this->setProperty(self::COLOR, '#FF0000');
        $this->setProperty('{DAV:}displayname', 'My calendar');

        $this->assertSame(2, $this->countRows());
        $this->assertSame('#FF0000', $this->getProperty(self::COLOR));
        $this->assertSame('My calendar', $this->getProperty('{DAV:}displayname'));
    }

    public function testTheSamePropertyOnAnotherPathIsIndependent(): void
    {
        $other = 'calendars/test_user/other';

        $this->setProperty(self::COLOR, '#FF0000');
        $this->setProperty(self::COLOR, '#00FF00', $other);

        $this->assertSame('#FF0000', $this->getProperty(self::COLOR));
        $this->assertSame('#00FF00', $this->getProperty(self::COLOR, $other));
    }

    public function testRemovingAPropertyDeletesItsRow(): void
    {
        $this->setProperty(self::COLOR, '#FF0000');
        $this->setProperty(self::COLOR, null);

        $this->assertSame(0, $this->countRows());
        $this->assertNull($this->getProperty(self::COLOR));
    }
}
