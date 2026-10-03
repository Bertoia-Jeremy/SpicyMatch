<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\FaqCategory;
use App\Entity\FaqCategoryTranslation;
use App\Entity\FaqQuestion;
use App\Entity\FaqQuestionTranslation;
use App\Entity\Spices;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class FaqFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var array<string, array{fr: string, en: string, es: string}>
     */
    private const array CATEGORIES = [
        'general' => [
            'fr' => 'Général',
            'en' => 'General',
            'es' => 'General',
        ],
        'lab' => [
            'fr' => 'Le Lab',
            'en' => 'The Lab',
            'es' => 'El Lab',
        ],
        'catalog' => [
            'fr' => 'Épices et arômes',
            'en' => 'Spices and aromas',
            'es' => 'Especias y aromas',
        ],
        'academy' => [
            'fr' => 'Académie',
            'en' => 'Academy',
            'es' => 'Academia',
        ],
        'account' => [
            'fr' => 'Compte et données',
            'en' => 'Account and data',
            'es' => 'Cuenta y datos',
        ],
    ];

    /**
     * @var list<array{category: string, spices?: list<string>, fr: array{string, string}, en: array{string, string}, es: array{string, string}}>
     */
    private const array QUESTIONS = [
        [
            'category' => 'general',
            'fr' => [
                "Qu'est-ce que SpicyMatch ?",
                "SpicyMatch est un atelier en ligne pour comprendre et composer des mélanges d'épices. Le Lab propose des accords à partir des molécules aromatiques que les épices partagent, le catalogue explique chaque épice, molécule et saveur, et l'Académie permet de s'entraîner en jouant.",
            ],
            'en' => [
                'What is SpicyMatch?',
                'SpicyMatch is an online workshop to understand and build spice blends. The Lab suggests pairings based on the aromatic molecules spices share, the catalog explains every spice, molecule and flavour, and the Academy lets you practise through games.',
            ],
            'es' => [
                '¿Qué es SpicyMatch?',
                'SpicyMatch es un taller en línea para entender y crear mezclas de especias. El Lab propone combinaciones a partir de las moléculas aromáticas que comparten las especias, el catálogo explica cada especia, molécula y sabor, y la Academia permite practicar jugando.',
            ],
        ],
        [
            'category' => 'general',
            'fr' => [
                'SpicyMatch est-il gratuit ?',
                "Oui. Tout le savoir est gratuit et le restera : le Lab, le catalogue et les fiches sont accessibles sans abonnement. L'abonnement Premium sert à soutenir le projet et apporte du confort : navigation sans publicité et davantage de parties par jour à l'Académie.",
            ],
            'en' => [
                'Is SpicyMatch free?',
                'Yes. All the knowledge is free and will stay that way: the Lab, the catalog and every page are available without a subscription. The Premium subscription supports the project and adds comfort: browsing without ads and more games per day in the Academy.',
            ],
            'es' => [
                '¿SpicyMatch es gratuito?',
                'Sí. Todo el conocimiento es gratuito y lo seguirá siendo: el Lab, el catálogo y las fichas están disponibles sin suscripción. La suscripción Premium sirve para apoyar el proyecto y aporta comodidad: navegación sin publicidad y más partidas al día en la Academia.',
            ],
        ],
        [
            'category' => 'general',
            'fr' => [
                'Pourquoi y a-t-il des publicités ?',
                "Les publicités financent l'hébergement et le développement. Elles proviennent de régies éthiques, sans pistage ni profilage, et n'apparaissent jamais pendant une partie ni sur vos recettes. Les membres Premium n'en voient aucune.",
            ],
            'en' => [
                'Why are there ads?',
                'Ads pay for hosting and development. They come from ethical ad networks, with no tracking or profiling, and never appear during a game or on your recipes. Premium members see none at all.',
            ],
            'es' => [
                '¿Por qué hay anuncios?',
                'Los anuncios financian el alojamiento y el desarrollo. Proceden de redes publicitarias éticas, sin rastreo ni perfilado, y nunca aparecen durante una partida ni en sus recetas. Los miembros Premium no ven ninguno.',
            ],
        ],
        [
            'category' => 'lab',
            'fr' => [
                'Comment SpicyMatch sait-il que deux épices vont bien ensemble ?',
                "Chaque épice contient des molécules aromatiques. Le Lab compare celles que les épices ont en commun, en donnant plus de poids aux molécules réellement perceptibles. Une épice n'est proposée que si elle partage au moins une molécule active avec chacune des épices déjà présentes dans le mortier.",
            ],
            'en' => [
                'How does SpicyMatch know two spices go well together?',
                'Every spice contains aromatic molecules. The Lab compares the ones spices have in common, giving more weight to molecules you can actually perceive. A spice is only suggested if it shares at least one active molecule with each spice already in the mortar.',
            ],
            'es' => [
                '¿Cómo sabe SpicyMatch que dos especias combinan bien?',
                'Cada especia contiene moléculas aromáticas. El Lab compara las que tienen en común, dando más peso a las moléculas realmente perceptibles. Una especia solo se propone si comparte al menos una molécula activa con cada una de las especias que ya están en el mortero.',
            ],
        ],
        [
            'category' => 'lab',
            'fr' => [
                'Que signifient « Excellent », « Harmonieux » et « Audacieux » ?',
                "Ces libellés résument la force de l'accord. Excellent : les épices partagent de nombreuses notes marquantes. Harmonieux : un accord solide et équilibré. Audacieux : un lien plus ténu, à essayer pour sortir des sentiers battus.",
            ],
            'en' => [
                'What do “Excellent”, “Harmonious” and “Bold” mean?',
                'These labels sum up how strong the pairing is. Excellent: the spices share many prominent notes. Harmonious: a solid, balanced pairing. Bold: a looser link, worth trying to step off the beaten path.',
            ],
            'es' => [
                '¿Qué significan «Excelente», «Armonioso» y «Audaz»?',
                'Estas etiquetas resumen la fuerza de la combinación. Excelente: las especias comparten muchas notas destacadas. Armonioso: una combinación sólida y equilibrada. Audaz: un vínculo más sutil, para probar algo fuera de lo común.',
            ],
        ],
        [
            'category' => 'lab',
            'fr' => [
                'Faut-il un compte pour utiliser le Lab ?',
                'Non. Vous pouvez composer, finaliser et consulter un mélange sans compte. Un compte gratuit permet en plus de renommer vos mélanges, de les mettre en favori et de retrouver votre historique. Les mélanges créés avant la connexion sont rattachés à votre compte.',
            ],
            'en' => [
                'Do I need an account to use the Lab?',
                'No. You can compose, finalise and view a blend without an account. A free account also lets you rename your blends, mark them as favourites and keep your history. Blends created before you sign in are attached to your account.',
            ],
            'es' => [
                '¿Necesito una cuenta para usar el Lab?',
                'No. Puede componer, finalizar y consultar una mezcla sin cuenta. Una cuenta gratuita permite además renombrar sus mezclas, marcarlas como favoritas y conservar su historial. Las mezclas creadas antes de iniciar sesión se vinculan a su cuenta.',
            ],
        ],
        [
            'category' => 'catalog',
            'fr' => [
                "Qu'est-ce qu'une molécule aromatique ?",
                "C'est un composé volatil qui donne à une épice une partie de son odeur et de son goût : notes citronnées, boisées, anisées… Une même molécule se retrouve souvent dans plusieurs épices, et c'est ce qui crée des ponts entre elles.",
            ],
            'en' => [
                'What is an aromatic molecule?',
                'It is a volatile compound that gives a spice part of its smell and taste: lemony, woody, aniseed notes… The same molecule is often found in several spices, which is what builds bridges between them.',
            ],
            'es' => [
                '¿Qué es una molécula aromática?',
                'Es un compuesto volátil que aporta a una especia parte de su olor y su sabor: notas cítricas, amaderadas, anisadas… Una misma molécula suele encontrarse en varias especias, y eso es lo que crea puentes entre ellas.',
            ],
        ],
        [
            'category' => 'catalog',
            'fr' => [
                'Les fiches donnent-elles des conseils de santé ?',
                "Non. SpicyMatch parle de goût, d'arômes et de cuisine. La rubrique « Bon à savoir » de chaque épice donne des conseils d'achat, de conservation et, le cas échéant, des précautions d'usage. Pour toute question de santé, adressez-vous à un professionnel.",
            ],
            'en' => [
                'Do the pages give health advice?',
                'No. SpicyMatch is about taste, aromas and cooking. The “Good to know” section of each spice gives buying and storage tips and, where relevant, usage precautions. For any health question, ask a professional.',
            ],
            'es' => [
                '¿Las fichas dan consejos de salud?',
                'No. SpicyMatch habla de sabor, aromas y cocina. La sección «Conviene saber» de cada especia ofrece consejos de compra y conservación y, cuando procede, precauciones de uso. Para cualquier pregunta de salud, consulte a un profesional.',
            ],
        ],
        [
            'category' => 'catalog',
            'spices' => ['cannelle'],
            'fr' => [
                'Cannelle de Ceylan ou cannelle casse : quelle différence ?',
                "La cannelle de Ceylan se présente en bâtons faits de fines couches d'écorce roulées, friables, au goût doux et floral. La casse forme une seule couche épaisse et dure, au goût plus puissant et piquant. La première se marie aux desserts délicats, la seconde tient tête aux plats mijotés.",
            ],
            'en' => [
                'Ceylon or cassia cinnamon: what is the difference?',
                'Ceylon cinnamon comes as sticks made of thin, brittle layers of rolled bark, with a soft, floral taste. Cassia forms a single thick, hard layer with a stronger, sharper taste. The first suits delicate desserts, the second stands up to slow-cooked dishes.',
            ],
            'es' => [
                'Canela de Ceilán o canela cassia: ¿cuál es la diferencia?',
                'La canela de Ceilán se presenta en ramas formadas por finas capas de corteza enrolladas, quebradizas, de sabor suave y floral. La cassia forma una sola capa gruesa y dura, de sabor más intenso y picante. La primera combina con postres delicados, la segunda aguanta guisos largos.',
            ],
        ],
        [
            'category' => 'academy',
            'fr' => [
                "Comment fonctionnent les jeux de l'Académie ?",
                "Les jeux demandent un compte gratuit. Chaque partie rapporte de l'expérience, fait monter votre niveau et débloque des badges. Le nombre de parties par jeu est limité chaque jour, avec une limite plus haute en Premium, et un jeu du jour rapporte un bonus.",
            ],
            'en' => [
                'How do the Academy games work?',
                'Games require a free account. Each game earns experience, raises your level and unlocks badges. The number of games per mode is limited each day, with a higher limit for Premium, and a game of the day earns a bonus.',
            ],
            'es' => [
                '¿Cómo funcionan los juegos de la Academia?',
                'Los juegos requieren una cuenta gratuita. Cada partida otorga experiencia, sube su nivel y desbloquea insignias. El número de partidas por juego está limitado cada día, con un límite más alto en Premium, y un juego del día otorga una bonificación.',
            ],
        ],
        [
            'category' => 'academy',
            'fr' => [
                'Puis-je désactiver la progression et les badges ?',
                "Oui, depuis les paramètres de votre profil. Les niveaux, l'expérience et les badges disparaissent alors de l'interface. Les jeux de l'Académie reposant sur la progression, ils ne sont plus accessibles tant qu'elle est désactivée.",
            ],
            'en' => [
                'Can I turn off progression and badges?',
                'Yes, from your profile settings. Levels, experience and badges then disappear from the interface. Since the Academy games rely on progression, they are unavailable while it is turned off.',
            ],
            'es' => [
                '¿Puedo desactivar la progresión y las insignias?',
                'Sí, desde los ajustes de su perfil. Los niveles, la experiencia y las insignias desaparecen entonces de la interfaz. Como los juegos de la Academia dependen de la progresión, no están disponibles mientras esté desactivada.',
            ],
        ],
        [
            'category' => 'account',
            'fr' => [
                'Quelles données conservez-vous ?',
                "Uniquement ce qui est nécessaire au fonctionnement du site : votre compte, vos mélanges et votre progression. Aucun traceur publicitaire n'est utilisé. Vous pouvez exporter vos données depuis votre profil, et la suppression du compte anonymise vos informations.",
            ],
            'en' => [
                'What data do you keep?',
                'Only what the site needs to work: your account, your blends and your progression. No advertising trackers are used. You can export your data from your profile, and deleting your account anonymises your information.',
            ],
            'es' => [
                '¿Qué datos conservan?',
                'Solo lo necesario para el funcionamiento del sitio: su cuenta, sus mezclas y su progresión. No se utiliza ningún rastreador publicitario. Puede exportar sus datos desde su perfil, y la eliminación de la cuenta anonimiza su información.',
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        $position = 0;
        foreach (self::CATEGORIES as $code => $names) {
            $position += 10;
            $existing = $manager->getRepository(FaqCategory::class)->findOneBy([
                'code' => $code,
            ]);
            if ($existing instanceof FaqCategory) {
                $categories[$code] = $existing;

                continue;
            }
            $category = new FaqCategory()
                ->setCode($code)
                ->setName($names['fr'])
                ->setPosition($position);
            foreach (['en', 'es'] as $locale) {
                $category->addTranslation(new FaqCategoryTranslation()
                    ->setLocale($locale)
                    ->setReviewed(false)
                    ->setName($names[$locale]));
            }
            $manager->persist($category);
            $categories[$code] = $category;
        }

        $positions = [];
        foreach (self::QUESTIONS as $data) {
            $code = $data['category'];
            $positions[$code] = ($positions[$code] ?? 0) + 10;
            if ($manager->getRepository(FaqQuestion::class)->count([
                'question' => $data['fr'][0],
            ]) > 0) {
                continue;
            }
            $question = new FaqQuestion()
                ->setCategory($categories[$code])
                ->setQuestion($data['fr'][0])
                ->setAnswer($data['fr'][1])
                ->setPosition($positions[$code])
                ->setPublished(true);
            foreach (['en', 'es'] as $locale) {
                $question->addTranslation(new FaqQuestionTranslation()
                    ->setLocale($locale)
                    ->setReviewed(false)
                    ->setQuestion($data[$locale][0])
                    ->setAnswer($data[$locale][1]));
            }
            foreach ($data['spices'] ?? [] as $slug) {
                $spice = $manager->getRepository(Spices::class)->findOneBy([
                    'slug' => $slug,
                ]);
                if ($spice instanceof Spices) {
                    $question->addSpice($spice);
                }
            }
            $manager->persist($question);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['faq'];
    }
}
