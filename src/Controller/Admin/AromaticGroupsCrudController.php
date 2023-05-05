<?php

namespace App\Controller\Admin;

use App\Entity\AromaticGroups;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class AromaticGroupsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AromaticGroups::class;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
