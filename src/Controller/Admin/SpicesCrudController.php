<?php

namespace App\Controller\Admin;

use App\Entity\Spices;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class SpicesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Spices::class;
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
