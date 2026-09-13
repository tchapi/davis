<?php

namespace App\Repository;

use App\Entity\AddressBook;
use App\Entity\Card;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AddressBook|null find($id, $lockMode = null, $lockVersion = null)
 * @method AddressBook|null findOneBy(array $criteria, array $orderBy = null)
 * @method AddressBook[]    findAll()
 * @method AddressBook[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AddressBookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressBook::class);
    }

    /**
     * Counts the cards of several address books at once, so that listing a principal's address
     * books costs a single query instead of one per address book.
     *
     * @param int[] $addressBookIds
     *
     * @return array<int, int> count per address book id, including the address books that hold nothing
     */
    public function countCardsByAddressBook(array $addressBookIds): array
    {
        $counts = array_fill_keys($addressBookIds, 0);

        if (!$addressBookIds) {
            return $counts;
        }

        $results = $this->getEntityManager()->getRepository(Card::class)
            ->createQueryBuilder('c')
            ->select('IDENTITY(c.addressBook) AS addressBookId, COUNT(c.id) AS count')
            ->where('c.addressBook IN (:addressBookIds)')
            ->setParameter('addressBookIds', $addressBookIds)
            ->groupBy('c.addressBook')
            ->getQuery()
            ->getResult();

        foreach ($results as $result) {
            $counts[(int) $result['addressBookId']] = (int) $result['count'];
        }

        return $counts;
    }
}
