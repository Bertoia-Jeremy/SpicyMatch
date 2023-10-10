<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/epices")
 */
class AromaticGroupsController extends AbstractController
{
    /**
     * @Route("/groupes_aromatiques", name="index_aromatic_groups")
     */
    public function index(): Response
    {
        return $this->render('aromatic_groups/index.html.twig', [
            'controller_name' => 'AromaticGroupsController',
        ]);
    }
}
