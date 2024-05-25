<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalNoticeController extends AbstractController
{
    #[Route('/legalNotice', name: 'app_legalNotice')]
    public function index(): Response
    {
        return $this->render('legal_notice/index.html.twig', [
            'controller_name' => 'LegalNoticeController',
        ]);
    }
}
