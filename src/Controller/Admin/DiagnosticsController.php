<?php

namespace App\Controller\Admin;

use App\Services\Diagnostics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DiagnosticsController extends AbstractController
{
    #[Route('/dashboard/diagnostics', name: 'diagnostics')]
    public function diagnostics(Diagnostics $diagnostics): Response
    {
        return $this->render('diagnostics.html.twig', [
            'buckets' => $diagnostics->buckets(),
            'attentionCount' => $diagnostics->attentionCount(),
        ]);
    }
}
