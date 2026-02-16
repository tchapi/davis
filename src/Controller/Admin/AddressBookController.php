<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\Principal;
use App\Entity\User;
use App\Form\AddressBookType;
use App\Services\BirthdayService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/addressbooks', name: 'addressbook_')]
class AddressBookController extends AbstractController
{
    #[Route('/{userId}', name: 'index')]
    public function addressBooks(ManagerRegistry $doctrine, int $userId): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $username = $user->getUsername();
        $principalUri = Principal::PREFIX.$username;

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);
        $addressbooks = $doctrine->getRepository(AddressBook::class)->findByPrincipalUri($principalUri);

        return $this->render('addressbooks/index.html.twig', [
            'addressbooks' => $addressbooks,
            'principal' => $principal,
            'userId' => $userId,
        ]);
    }

    #[Route('/{userId}/new', name: 'create')]
    #[Route('/{userId}/edit/{id}', name: 'edit', requirements: ['id' => "\d+"])]
    public function addressbookCreate(ManagerRegistry $doctrine, Request $request, int $userId, ?int $id, TranslatorInterface $trans, BirthdayService $birthdayService): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $username = $user->getUsername();
        $principalUri = Principal::PREFIX.$username;

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);

        if (!$principal) {
            throw $this->createNotFoundException('User not found');
        }

        if ($id) {
            $addressbook = $doctrine->getRepository(AddressBook::class)->findOneById($id);
            if (!$addressbook) {
                throw $this->createNotFoundException('Address book not found');
            }
        } else {
            $addressbook = new AddressBook();
        }

        $isBirthdayCalendarEnabled = $this->getParameter('caldav_enabled') && $this->getParameter('carddav_enabled');

        $form = $this->createForm(AddressBookType::class, $addressbook, ['new' => !$id, 'birthday_calendar_enabled' => $isBirthdayCalendarEnabled]);

        if ($isBirthdayCalendarEnabled) {
            $form->get('includedInBirthdayCalendar')->setData($addressbook->isIncludedInBirthdayCalendar());
        }
        $form->get('principalUri')->setData($principalUri);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();

            $entityManager->persist($addressbook);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('addressbooks.saved'));

            if ($isBirthdayCalendarEnabled && true === $form->get('includedInBirthdayCalendar')->getData()) {
                $addressbook->setIncludedInBirthdayCalendar(true);
            } else {
                $addressbook->setIncludedInBirthdayCalendar(false);
            }

            if ($isBirthdayCalendarEnabled) {
                // Let's sync the user birthday calendar if needed
                $birthdayService->syncUser($username);
            }

            return $this->redirectToRoute('addressbook_index', ['userId' => $userId]);
        }

        return $this->render('addressbooks/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'userId' => $userId,
            'addressbook' => $addressbook,
        ]);
    }

    #[Route('/{userId}/delete/{id}', name: 'delete', requirements: ['id' => "\d+"])]
    public function addressbookDelete(ManagerRegistry $doctrine, int $userId, string $id, TranslatorInterface $trans, BirthdayService $birthdayService): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $addressbook = $doctrine->getRepository(AddressBook::class)->findOneById($id);
        if (!$addressbook) {
            throw $this->createNotFoundException('Address Book not found');
        }

        $entityManager = $doctrine->getManager();

        foreach ($addressbook->getCards() ?? [] as $card) {
            $entityManager->remove($card);
        }
        foreach ($addressbook->getChanges() ?? [] as $change) {
            $entityManager->remove($change);
        }
        $entityManager->remove($addressbook);

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('addressbooks.deleted'));

        $isBirthdayCalendarEnabled = $this->getParameter('caldav_enabled') && $this->getParameter('carddav_enabled');
        if ($isBirthdayCalendarEnabled) {
            // Let's sync the user birthday calendar if needed
            $birthdayService->syncUser($user->getUsername());
        }

        return $this->redirectToRoute('addressbook_index', ['userId' => $userId]);
    }
}
