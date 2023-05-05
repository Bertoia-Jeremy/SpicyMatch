<?php

namespace App\Controller\Admin;

use App\Entity\AromaticCompound;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class AromaticCompoundCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AromaticCompound::class;
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
