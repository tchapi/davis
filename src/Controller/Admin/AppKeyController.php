<?php

namespace App\Controller\Admin;

use App\Entity\AppKey;
use App\Form\AppKeyType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/app-keys', name: 'app_key_')]
class AppKeyController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ManagerRegistry $doctrine, Request $request): Response
    {
        return $this->render('app_keys/index.html.twig', [
            'keys' => $doctrine->getRepository(AppKey::class)->findAllNewestFirst(),
            'now' => new \DateTimeImmutable(),
            // We use the session, not a flash, because flashes are shown as toasts.
            // remove() returns the secret and deletes it, so it is only shown once.
            'created' => $request->getSession()->remove('app_key_created'),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(ManagerRegistry $doctrine, Request $request, TranslatorInterface $trans): Response
    {
        $form = $this->createForm(AppKeyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $key = AppKey::create(
                $form->get('name')->getData(),
                AppKey::parseScope((string) $form->get('scope')->getData()),
                $form->get('expiresAt')->getData()
            );
            $secret = $key->issueSecret();

            $entityManager = $doctrine->getManager();
            $entityManager->persist($key);
            $entityManager->flush();

            $request->getSession()->set('app_key_created', [
                'name' => $key->getName(),
                'appId' => $key->getAppId(),
                'secret' => $secret,
            ]);

            $this->addFlash('success', $trans->trans('app_keys.saved'));

            return $this->redirectToRoute('app_key_index');
        }

        return $this->render('app_keys/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/revoke/{id}', name: 'revoke', methods: ['POST'])]
    public function revoke(ManagerRegistry $doctrine, Request $request, #[MapEntity] AppKey $appKey, TranslatorInterface $trans): Response
    {
        if (!$this->isCsrfTokenValid('admin_action', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $appKey->revoke(new \DateTimeImmutable());
        $doctrine->getManager()->flush();

        $this->addFlash('success', $trans->trans('app_keys.revoked'));

        return $this->redirectToRoute('app_key_index');
    }
}
