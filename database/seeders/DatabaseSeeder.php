<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========================
        // USERS
        // ========================
        $admin = User::create([
            'name'              => 'Admin e-Sup\'M',
            'email'             => 'admin@esup-m.ci',
            'password'          => Hash::make('Admin@2024!'),
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name'              => 'Préparateur Test',
            'email'             => 'prep@esup-m.ci',
            'password'          => Hash::make('Prep@2024!'),
            'role'              => 'preparateur',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name'              => 'Livreur Test',
            'email'             => 'livreur@esup-m.ci',
            'password'          => Hash::make('Livreur@2024!'),
            'role'              => 'livreur',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name'              => 'Client Test',
            'email'             => 'client@esup-m.ci',
            'password'          => Hash::make('Client@2024!'),
            'role'              => 'client',
            'email_verified_at' => now(),
            'loyalty_points'    => 500,
        ]);

        // ========================
        // CATEGORIES (Rayons e-Sup'M)
        // ========================
        $categories = [
            ['name' => 'Produits Frais',         'slug' => 'produits-frais',        'icon' => '🥗', 'color' => '#4CAF50', 'sort_order' => 1],
            ['name' => 'Épicerie Salée',          'slug' => 'epicerie-salee',        'icon' => '🥫', 'color' => '#FF9800', 'sort_order' => 2],
            ['name' => 'Épicerie Sucrée',         'slug' => 'epicerie-sucree',       'icon' => '🍬', 'color' => '#E91E63', 'sort_order' => 3],
            ['name' => 'Espace Soif',             'slug' => 'espace-soif',           'icon' => '🥤', 'color' => '#2196F3', 'sort_order' => 4],
            ['name' => 'Boucherie & Poissonnerie','slug' => 'boucherie-poissonnerie','icon' => '🥩', 'color' => '#F44336', 'sort_order' => 5],
            ['name' => 'Pain & Pâtisserie',       'slug' => 'pain-patisserie',       'icon' => '🍞', 'color' => '#FF5722', 'sort_order' => 6],
            ['name' => 'Bébé & Confort',          'slug' => 'bebe-confort',          'icon' => '👶', 'color' => '#9C27B0', 'sort_order' => 7],
            ['name' => 'Hygiène & Beauté',        'slug' => 'hygiene-beaute',        'icon' => '💄', 'color' => '#00BCD4', 'sort_order' => 8],
            ['name' => 'Diététique & Santé',      'slug' => 'dietetique-sante',      'icon' => '🌿', 'color' => '#8BC34A', 'sort_order' => 9],
            ['name' => 'Entretien & Ménage',      'slug' => 'entretien-menage',      'icon' => '🧹', 'color' => '#607D8B', 'sort_order' => 10],
            ['name' => 'Non Alimentaire',         'slug' => 'non-alimentaire',       'icon' => '🏠', 'color' => '#795548', 'sort_order' => 11],
            ['name' => '½ Gros & Gros',           'slug' => 'demi-gros-gros',        'icon' => '📦', 'color' => '#3F51B5', 'sort_order' => 12],
            ['name' => 'Rayon Premium',           'slug' => 'rayon-premium',         'icon' => '⭐', 'color' => '#FFD700', 'sort_order' => 13, 'is_premium' => true],
        ];

        $subCategories = [
            'produits-frais'        => ['Produits Laitiers', 'Fruits', 'Légumes', 'Produits Complémentaires'],
            'epicerie-salee'        => ['Denrée de Base', 'Conserves', 'Huiles & Vinaigres', 'Condiments', 'Épices & Herbes', 'Snacks & Apéritifs', 'Féculents', 'Légumineuses & Graines', 'Tubercules', 'Farine'],
            'epicerie-sucree'       => ['Biscuits', 'Chocolats & Confiseries', 'Confitures & Pâtes à Tartiner', 'Céréales & Petit-Déj', 'Sucre', 'Glaces'],
            'espace-soif'           => ['Eaux Plates & Gazeuses', 'Jus de Fruits', 'Sodas & Boissons Sucrées', 'Spiritueux', 'Thé, Café & Infusions', 'Bières', 'Vins', 'Champagne & Mousseux', 'Apéritifs'],
            'boucherie-poissonnerie'=> ['Boucherie', 'Poissonnerie', 'Charcuterie', 'Fruits de Mer'],
            'pain-patisserie'       => ['Pain', 'Viennoiseries', 'Brioches & Gâteaux', 'Biscuits, Crêpes & Cookies'],
            'bebe-confort'          => ['Alimentation Bébé', 'Hygiène & Soins Bébé', 'Sommeil & Détente', 'Transport Bébé'],
            'hygiene-beaute'        => ['Soins du Corps', 'Soins du Visage', 'Cheveux & Coiffure', 'Hygiène Bucco-Dentaire', 'Hygiène Intime', 'Rasage', 'Parfumerie & Déodorant'],
            'entretien-menage'      => ['Lessive', 'Soins du Linge', 'Nettoyants', 'Désinfectants', 'Vaisselle & Cuisine', 'Sacs Poubelle', 'Insecticides', 'Désodorisants'],
        ];

        $parentMap = [];
        foreach ($categories as $catData) {
            $cat = Category::create($catData);
            $parentMap[$catData['slug']] = $cat->id;
        }

        foreach ($subCategories as $parentSlug => $subs) {
            $parentId = $parentMap[$parentSlug] ?? null;
            if (!$parentId) continue;
            foreach ($subs as $idx => $subName) {
                Category::create([
                    'parent_id'  => $parentId,
                    'name'       => $subName,
                    'slug'       => Str::slug($subName) . '-' . $parentSlug,
                    'sort_order' => $idx + 1,
                ]);
            }
        }

        // ========================
        // BADGES
        // ========================
        $badges = [
            ['name' => 'Première Commande', 'type' => 'purchase', 'condition_key' => 'orders_count',        'condition_value' => 1,    'points_reward' => 50,  'description' => 'Vous avez passé votre 1ère commande !',       'image' => 'badges/first-order.png'],
            ['name' => 'Client Fidèle',     'type' => 'purchase', 'condition_key' => 'orders_count',        'condition_value' => 10,   'points_reward' => 200, 'description' => '10 commandes passées !',                       'image' => 'badges/loyal.png'],
            ['name' => 'Grand Acheteur',    'type' => 'purchase', 'condition_key' => 'orders_count',        'condition_value' => 50,   'points_reward' => 500, 'description' => '50 commandes passées - vous êtes incroyable !', 'image' => 'badges/big-buyer.png'],
            ['name' => 'Généreux',          'type' => 'charity',  'condition_key' => 'charity_donations',   'condition_value' => 1,    'points_reward' => 100, 'description' => 'Votre premier don alimentaire.',                 'image' => 'badges/generous.png'],
            ['name' => 'Bienfaiteur',       'type' => 'charity',  'condition_key' => 'charity_amount',      'condition_value' => 50000,'points_reward' => 500, 'description' => '50 000 FCFA de dons au total.',                  'image' => 'badges/benefactor.png'],
            ['name' => 'Critique Expert',   'type' => 'review',   'condition_key' => 'reviews_count',       'condition_value' => 5,    'points_reward' => 100, 'description' => '5 avis publiés.',                               'image' => 'badges/reviewer.png'],
            ['name' => 'Champion',          'type' => 'game',     'condition_key' => 'games_won',           'condition_value' => 1,    'points_reward' => 150, 'description' => 'Votre premier jeu gagné !',                     'image' => 'badges/champion.png'],
            ['name' => 'Abonné Premium',    'type' => 'subscription','condition_key'=>'subscriptions_count','condition_value' => 1,    'points_reward' => 200, 'description' => 'Vous avez souscrit à un abonnement.',            'image' => 'badges/subscriber.png'],
            ['name' => 'Fidèle 3 Mois',     'type' => 'purchase', 'condition_key' => 'consecutive_months', 'condition_value' => 3,    'points_reward' => 300, 'description' => '3 mois consécutifs de commandes.',               'image' => 'badges/three-months.png'],
            ['name' => 'VIP Platinum',      'type' => 'loyalty',  'condition_key' => 'loyalty_points_total','condition_value' => 50000,'points_reward' => 1000,'description' => '50 000 points gagnés - Statut Platinum !',       'image' => 'badges/vip.png'],
        ];

        foreach ($badges as $badge) {
            Badge::create($badge);
        }

        // ========================
        // SAMPLE GAME (Quiz)
        // ========================
        $quiz = \App\Models\Game::create([
            'name'                => 'Quiz Alimentaire e-Sup\'M',
            'type'                => 'quiz',
            'status'              => 'active',
            'is_open_to_all'      => true,
            'requires_registration' => true,
            'loyalty_points_prize'=> 100,
            'time_limit_seconds'  => 30,
            'prizes'              => json_encode(['Points de fidélité', 'Bon de réduction 5%']),
            'auto_activate_day'   => 'mardi',
            'duration_days'       => 7,
            'participation_cooldown_days' => 3,
        ]);

        $questions = [
            ['question' => 'Quel fruit est connu pour être le plus riche en vitamine C ?', 'options' => json_encode([['text'=>'Pomme','is_correct'=>false],['text'=>'Orange','is_correct'=>false],['text'=>'Citron','is_correct'=>false],['text'=>'Goyave','is_correct'=>true]]), 'correct_answer' => 'Goyave', 'theme' => 'nutrition', 'points' => 10, 'time_limit_seconds' => 20],
            ['question' => 'Quelle céréale est la base du couscous ?', 'options' => json_encode([['text'=>'Blé','is_correct'=>true],['text'=>'Riz','is_correct'=>false],['text'=>'Maïs','is_correct'=>false],['text'=>'Seigle','is_correct'=>false]]), 'correct_answer' => 'Blé', 'theme' => 'produit', 'points' => 10, 'time_limit_seconds' => 20],
            ['question' => 'Combien de grammes de protéines contient un œuf en moyenne ?', 'options' => json_encode([['text'=>'3g','is_correct'=>false],['text'=>'6g','is_correct'=>true],['text'=>'10g','is_correct'=>false],['text'=>'15g','is_correct'=>false]]), 'correct_answer' => '6g', 'theme' => 'nutrition', 'points' => 15, 'time_limit_seconds' => 25],
        ];

        foreach ($questions as $idx => $q) {
            $quiz->quizQuestions()->create([...$q, 'sort_order' => $idx]);
        }

        // Roue
        $roue = \App\Models\Game::create([
            'name'   => 'Roue e-Sup\'M',
            'type'   => 'roue',
            'status' => 'active',
            'requires_purchase'   => true,
            'min_purchase_amount' => 15000,
            'participation_cooldown_days' => 15,
        ]);

        $prizes_roue = [
            ['label'=>'Retentez !',     'type'=>'retry',    'probability'=>40, 'points'=>0],
            ['label'=>'100 Points',     'type'=>'loyalty',  'probability'=>30, 'points'=>100],
            ['label'=>'200 Points',     'type'=>'loyalty',  'probability'=>15, 'points'=>200],
            ['label'=>'5% de réduction','type'=>'discount', 'probability'=>10, 'points'=>50],
            ['label'=>'Livraison gratuite','type'=>'shipping','probability'=>4, 'points'=>0],
            ['label'=>'🎁 Cadeau surprise','type'=>'gift',  'probability'=>1, 'points'=>500],
        ];

        foreach ($prizes_roue as $prize) {
            $roue->wheelPrizes()->create($prize);
        }

        // ========================
        // ADVERTISEMENTS (demo)
        // ========================
        \App\Models\Advertisement::create(['title'=>'Espace Publicité Large','position'=>'large_center','page'=>'home','is_flashing'=>true,'slide_count'=>3,'is_active'=>true,'image'=>'ads/placeholder-large.jpg']);
        \App\Models\Advertisement::create(['title'=>'Espace Pub Gauche','position'=>'left','page'=>'home','slide_count'=>2,'is_active'=>true,'image'=>'ads/placeholder-left.jpg']);
        \App\Models\Advertisement::create(['title'=>'Espace Pub Droite','position'=>'right','page'=>'home','slide_count'=>2,'is_active'=>true,'image'=>'ads/placeholder-right.jpg']);

        $this->command->info('✅ e-Sup\'M database seeded successfully!');
        $this->command->info('👤 Admin: admin@esup-m.ci / Admin@2024!');
        $this->command->info('🛍️ Client: client@esup-m.ci / Client@2024!');
    }
}
