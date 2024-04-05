<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Entity\SpicyType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Smknstd\FakerPicsumImages\FakerPicsumImagesProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SpicesFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private string $path
    )
    {
        
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->addProvider(new FakerPicsumImagesProvider($faker));

        for ($i = 0; $i < 36; ++$i) {
            copy($faker->imageUrl(width: 800, height: 600), "C:\wamp64\www\sites\spicymatch\public\uploads\spices_$i.jpg");

            $spice = new Spices();
            $spice->setName($faker->name())
                ->setDescription($faker->text(260))
                ->setCooking($faker->text(260))
                ->setInformations($faker->text(260))
                ->setBenefits($faker->text(260))
                ->setCreatedAt($faker->dateTime())
                ->setUpdatedAt($faker->dateTime())
                ->setAromaticGroups($this->getReference('AromaticGroup_'.rand(0, 11), AromaticGroups::class))
                ->setSpicyType($this->getReference('SpicyType_'.rand(0, 5), SpicyType::class))
                ->setImageFile(new UploadedFile($this->path."spices_$i.jpg", "spices_$i.jpg", null, null, true));
            //->setImageFile(new UploadedFile("/home/jbertoia/Images/cannelle_{$i}.webp", 'test.webp', null, null, true));

            $manager->persist($spice);
            $this->addReference('Spice_'.$i, $spice);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AromaticGroupsFixtures::class,
            SpicyTypeFixtures::class,
        ];
    }
}
