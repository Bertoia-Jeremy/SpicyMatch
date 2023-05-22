<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/epices")
 */
class SpicesController extends AbstractController
{
    /**
     * @Route("/", name="index_spices")
     */
    public function index(): Response
    {
        return $this->render('spices/index.html.twig', [
            'controller_name' => 'SpicesController',
        ]);
    }
}
