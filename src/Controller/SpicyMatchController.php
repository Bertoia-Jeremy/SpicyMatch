<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch')]
class SpicyMatchController extends AbstractController
{
    private \App\Repository\SpicesRepository $spicesRepository;

    public function __construct(
        SpicesRepository $spicesRepository,
    ) {
        $this->spicesRepository = $spicesRepository;
    }

    #[Route('/', name: 'index_spicy_match')]
    public function index(Request $request): Response
    {
        /*
         * Récupérer les ids, faire un foreach récupérer les composés, récupérer à la fin toutes les épices par rapport au composé
         */
        // Prendre exemple sur adminUserController dans le projet de la billeterie
        // admin/user/index.html.twig
        // Pour la macro : https://stackoverflow.com/questions/23315104/putting-twig-generates-html-into-a-js-variable
        if (! $request->isXmlHttpRequest()) {
            $spices = $this->spicesRepository->findAll();

            return $this->render('spicy_match/index.html.twig', [
                'spices' => $spices,
            ]);
        }
    }

    #[Route('/matcher', name: 'view_spicy_match', methods: ['POST'])]
    public function matcherView(Request $request): \Symfony\Component\HttpFoundation\JsonResponse
    {
        // TODO => sécuriser l'appel ajax
        // Récupération des IDs envoyés par ajax
        $spicesId = json_decode($request->getContent(), true);

        if (count($spicesId) === 0) {
            $spicesEntity = $this->spicesRepository->findAll();
        } else {
            // Récupération de tous les composés aromatiques
            $allAromaticsCompoundsIds = $this->getAllAromaticsCompounds(
                $spicesId
            );

            // Filtre et selection des composés aromatiques en commun
            $communAromaticsCompoundsIds = $this->getAromaticsCompoundsInCommon(
                $allAromaticsCompoundsIds,
                count($spicesId)
            );

            if (count($communAromaticsCompoundsIds['main']) + count(
                $communAromaticsCompoundsIds['secondary']
            ) === 0) {
                return $this->json([]);
            }

            // Récupération de toutes les épices possédant ces composés aromatiques par ordre d'affinités aux composés
            $spicesIdMatched = $this->spicesRepository->getByMainAromaticsCompounds(
                $communAromaticsCompoundsIds['main'],
                $communAromaticsCompoundsIds['secondary']
            );

            $spicesIdOrdered = $this->putSelectedIdAtTheTop($spicesIdMatched, $spicesId);

            $spicesEntity = $this->getSpicesEntity($spicesIdOrdered);
        }

        // Création des templates cards
        $template = $this->render('spicy_match/cards_spices_matched.html.twig', [
            'spices' => $spicesEntity,
            'spicesChecked' => $spicesId,
        ])->getContent();

        /* $token = $request->request->get('_token');

         if (is_string($token) && $this->isCsrfTokenValid('matcher', $token)) {
             // $this->userRepository->remove($user, true);
             return $this->json(['success' => true]);
         }
        */

        return $this->json(
            $template
        );
    }

    private function getAllAromaticsCompounds(array $spicesId): array
    {
        $mainAromaticsCompoundsIds = [];
        $secondaryAromaticsCompoundsIds = [];

        foreach ($spicesId as $id) {
            /** @var Spices $spice */
            $spice = $this->spicesRepository->findOneBy([
                'id' => (int) $id,
            ]);

            if ($spice !== null) {
                $arrayMainAromaticCompound = $spice->getAromaticsCompounds()
                    ->getIterator();
                $mainAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice(
                    $arrayMainAromaticCompound,
                    $mainAromaticsCompoundsIds
                );

                $arraySecondaryAromaticCoumpound = $spice->getSecondaryAromaticsCompounds()
                    ->getIterator();
                $secondaryAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice(
                    $arraySecondaryAromaticCoumpound,
                    $secondaryAromaticsCompoundsIds
                );
            }
        }

        return [
            'main' => $mainAromaticsCompoundsIds,
            'secondary' => $secondaryAromaticsCompoundsIds,
        ];
    }

    private function getAromaticsCompoundsInCommon(array $allAromaticsCompoundsIds, int $numberSpices): array
    {
        $mainCompounds = $allAromaticsCompoundsIds['main'];
        $secondaryCompounds = $allAromaticsCompoundsIds['secondary'];
        $mainCommon = $secondaryCommon = [];

        foreach ($mainCompounds as $id => $numberMatchMain) {
            $numberMatch = $numberMatchMain + ($secondaryCompounds[$id] ?? 0);

            if ($numberMatch === $numberSpices) {
                $mainCommon[] = $id;
            }
        }

        foreach ($secondaryCompounds as $id => $numberMatchSecondary) {
            $numberMatch = $numberMatchSecondary + ($mainCompounds[$id] ?? 0);

            if (($numberMatch === $numberSpices) && ! isset($mainCommon[$id])) {
                $secondaryCommon[] = $id;
            }
        }

        return [
            'main' => $mainCommon,
            'secondary' => $secondaryCommon,
        ];
    }

    private function getAromaticsCompoundsFromIteratorSpice(
        $iteratorAromaticCompound,
        array $allAromaticsCompoundsIds
    ): array {
        foreach ($iteratorAromaticCompound as $aromaticCompound) {
            $aromaticCompoundId = $aromaticCompound->getId();

            if (! array_key_exists($aromaticCompoundId, $allAromaticsCompoundsIds)) {
                $allAromaticsCompoundsIds[$aromaticCompoundId] = 1;
            } else {
                ++$allAromaticsCompoundsIds[$aromaticCompoundId];
            }
        }

        return $allAromaticsCompoundsIds;
    }

    private function getSpicesEntity(array $spicesOrderedByMatch): array
    {
        $spicesEntity = [];

        foreach ($spicesOrderedByMatch as $spice) {
            $spicesEntity[] = $this->spicesRepository->findOneBy([
                'id' => $spice['spices_id'],
            ]);
        }

        return $spicesEntity;
    }

    private function putSelectedIdAtTheTop(array $spicesIdMatched, $spicesId): array
    {
        $spicesIdChecked = [];

        foreach ($spicesIdMatched as $key => $value) {
            if (in_array($value['spices_id'], $spicesId, true)) {
                $spicesIdChecked['selected_' . $key] = $value;
                unset($spicesIdMatched[$key]);
            }
        }

        return array_merge($spicesIdChecked, $spicesIdMatched);
    }
}
