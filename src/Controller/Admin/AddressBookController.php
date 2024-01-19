<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\Principal;
use App\Form\AddressBookType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/adressbooks', name: 'addressbook_')]
class AddressBookController extends AbstractController
{
    #[Route('/{username}', name: 'index')]
    public function addressBooks(ManagerRegistry $doctrine, string $username): Response
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $addressbooks = $doctrine->getRepository(AddressBook::class)->findByPrincipalUri(Principal::PREFIX.$username);

        return $this->render('addressbooks/index.html.twig', [
            'addressbooks' => $addressbooks,
            'principal' => $principal,
            'username' => $username,
        ]);
    }

    #[Route('/{username}/new', name: 'create')]
    #[Route('/{username}/edit/{id}', name: 'edit', requirements: ['id' => "\d+"])]
    public function addressbookCreate(ManagerRegistry $doctrine, Request $request, string $username, ?int $id, TranslatorInterface $trans): Response
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

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

        $form = $this->createForm(AddressBookType::class, $addressbook, ['new' => !$id]);

        $form->get('principalUri')->setData(Principal::PREFIX.$username);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();

            $entityManager->persist($addressbook);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('addressbooks.saved'));

            return $this->redirectToRoute('addressbook_index', ['username' => $username]);
        }

        return $this->render('addressbooks/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'username' => $username,
            'addressbook' => $addressbook,
        ]);
    }

    #[Route('/{username}/delete/{id}', name: 'delete', requirements: ['id' => "\d+"])]
    public function addressbookDelete(ManagerRegistry $doctrine, string $username, string $id, TranslatorInterface $trans): Response
    {
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

        return $this->redirectToRoute('addressbook_index', ['username' => $username]);
    }
}
