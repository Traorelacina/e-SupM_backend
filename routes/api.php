<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\CharityController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductCategoryController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminGameController;
use App\Http\Controllers\Admin\AdminPartnerController;
use App\Http\Controllers\Admin\AdminAdvertisementController;
use App\Http\Controllers\Admin\AdminCharityController;
use App\Http\Controllers\Admin\AdminRecipeController;
use App\Http\Controllers\Admin\AdminStatsController;
use App\Http\Controllers\Admin\AdminDeliveryController;
use App\Http\Controllers\Admin\AdminCouponController;
use App\Http\Controllers\Admin\AdminFoodBoxController;
use App\Http\Controllers\PublicFoodBoxController;
use App\Http\Controllers\SelectiveSubscriptionController;
use App\Http\Controllers\Admin\AdminSelectiveSubscriptionController;
use App\Http\Controllers\Admin\ConseilController as AdminConseilController;
use App\Http\Controllers\ConseilController as PublicConseilController;

// ========================
// AUTH ROUTES (Public)
// ========================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');

    Route::get('/social/{provider}', [SocialAuthController::class, 'redirect']);
    Route::get('/social/{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('/social/token', [SocialAuthController::class, 'loginWithToken']);

    Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor']);
});

// ========================
// PUBLIC ROUTES
// ========================
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/tree', [CategoryController::class, 'tree']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/products', [CategoryController::class, 'products']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);
Route::get('/products/bestsellers', [ProductController::class, 'bestsellers']);
Route::get('/products/premium', [ProductController::class, 'premium']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/products/{id}/related', [ProductController::class, 'related']);

Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
Route::post('/search/visual', [SearchController::class, 'visualSearch']);

Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/promotions/flash', [PromotionController::class, 'flash']);
Route::get('/promotions/soldes', [PromotionController::class, 'soldes']);
Route::get('/promotions/destockage', [PromotionController::class, 'destockage']);

Route::get('/recipes', [RecipeController::class, 'index']);
Route::get('/recipes/{id}', [RecipeController::class, 'show']);

Route::get('/partners', [PartnerController::class, 'index']);
Route::get('/partners/{id}', [PartnerController::class, 'show']);
Route::post('/partners/apply', [PartnerController::class, 'apply']);

Route::get('/advertisements', [AdvertisementController::class, 'index']);
Route::get('/advertisements/{id}/click', [AdvertisementController::class, 'registerClick']);

// Les anciennes routes /games (index, winners, show) ont été supprimées
// car elles ne correspondent plus aux méthodes du nouveau GameController.

Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

Route::get('/charity/vouchers/check/{code}', [CharityController::class, 'checkVoucher']);

// Food Boxes (public)
Route::prefix('food-boxes')->group(function () {
    Route::get('/', [PublicFoodBoxController::class, 'index']);
    Route::get('/featured', [PublicFoodBoxController::class, 'featured']);
    Route::get('/search', [PublicFoodBoxController::class, 'search']);
    Route::get('/frequencies', [PublicFoodBoxController::class, 'frequencies']);
    Route::get('/{identifier}', [PublicFoodBoxController::class, 'show']);
    Route::get('/{id}/availability', [PublicFoodBoxController::class, 'checkAvailabilityEndpoint']);

    if (config('app.debug')) {
        Route::post('/clear-cache', [PublicFoodBoxController::class, 'clearCache']);
    }
});

// Cart (public — basé sur session/cookie)
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/add', [CartController::class, 'add']);
    Route::put('/item/{id}', [CartController::class, 'updateItem']);
    Route::delete('/item/{id}', [CartController::class, 'removeItem']);
    Route::delete('/', [CartController::class, 'clear']);
    Route::post('/coupon/apply', [CartController::class, 'applyCoupon']);
    Route::delete('/coupon', [CartController::class, 'removeCoupon']);
});

// ========================
// AUTHENTICATED USER ROUTES
// ========================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification']);
    Route::post('/auth/two-factor/enable', [AuthController::class, 'enableTwoFactor']);
    Route::post('/auth/two-factor/disable', [AuthController::class, 'disableTwoFactor']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);
    Route::delete('/profile', [ProfileController::class, 'deleteAccount']);

    // Addresses — toutes les méthodes dans ProfileController
    Route::get('/addresses', [ProfileController::class, 'addresses']);
    Route::post('/addresses', [ProfileController::class, 'storeAddress']);
    Route::put('/addresses/{id}', [ProfileController::class, 'updateAddress']);
    Route::delete('/addresses/{id}', [ProfileController::class, 'destroyAddress']);
    Route::put('/addresses/{id}/default', [ProfileController::class, 'setDefaultAddress']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/reorder', [OrderController::class, 'reorder']);

    Route::get('/deliveries/{orderId}/track', [DeliveryController::class, 'track']);

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
    Route::put('/subscriptions/{id}', [SubscriptionController::class, 'update']);
    Route::post('/subscriptions/{id}/suspend', [SubscriptionController::class, 'suspend']);
    Route::post('/subscriptions/{id}/resume', [SubscriptionController::class, 'resume']);
    Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'cancel']);
    Route::delete('/subscriptions/{id}/delete', [SubscriptionController::class, 'destroy']);
    Route::get('/subscriptions/{id}/history', [SubscriptionController::class, 'history']);

    // Loyalty
    Route::get('/loyalty', [LoyaltyController::class, 'dashboard']);
    Route::get('/loyalty/transactions', [LoyaltyController::class, 'transactions']);
    Route::get('/loyalty/badges', [LoyaltyController::class, 'badges']);
    Route::post('/loyalty/redeem', [LoyaltyController::class, 'redeem']);
    Route::get('/loyalty/leaderboard', [LoyaltyController::class, 'leaderboard']);

    // ========================
    // JEUX CONCOURS — NOUVELLES ROUTES
    // (remplacent les anciennes routes /games/*)
    // ========================
    Route::prefix('games')->group(function () {
        // Tableau de bord utilisateur
        Route::get('/me', [GameController::class, 'myGames']);

        // Défis
        Route::prefix('defis')->group(function () {
            Route::get('/',                   [GameController::class, 'defiIndex']);
            Route::get('/{id}',               [GameController::class, 'defiShow']);
            Route::get('/{id}/status',        [GameController::class, 'defiUserStatus']);
            Route::post('/{id}/participate',  [GameController::class, 'defiParticipate']);
            Route::post('/{id}/vote',         [GameController::class, 'defiVote']);
        });

        // Carte à gratter
        Route::prefix('scratch')->group(function () {
            Route::get('/',                   [GameController::class, 'scratchIndex']);
            Route::post('/{id}/scratch',      [GameController::class, 'scratchReveal']);
        });

        // Roue e-Sup'M
        Route::prefix('wheel')->group(function () {
            Route::get('/',                   [GameController::class, 'wheelAvailable']);
            Route::get('/history',            [GameController::class, 'wheelHistory']);
            Route::post('/{configId}/spin',   [GameController::class, 'wheelSpin']);
        });

        // Quiz
        Route::prefix('quiz')->group(function () {
            Route::get('/',                   [GameController::class, 'quizIndex']);
            Route::get('/{id}',               [GameController::class, 'quizShow']);
            Route::get('/{id}/leaderboard',   [GameController::class, 'quizLeaderboard']);
            Route::post('/{id}/submit',       [GameController::class, 'quizSubmit']);
        });

        // Battle
        Route::prefix('battle')->group(function () {
            Route::get('/',                   [GameController::class, 'battleIndex']);
            Route::get('/{id}',               [GameController::class, 'battleShow']);
            Route::post('/{id}/vote',         [GameController::class, 'battleVote']);
        });

        // Juste Prix
        Route::prefix('justeprix')->group(function () {
            Route::get('/',                   [GameController::class, 'justePrixIndex']);
            Route::get('/{id}',               [GameController::class, 'justePrixShow']);
            Route::post('/{id}/participate',  [GameController::class, 'justePrixParticipate']);
        });
    });

    // Reviews
    Route::post('/products/{id}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/add', [WishlistController::class, 'add']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'remove']);
    Route::post('/wishlist/to-cart', [WishlistController::class, 'moveToCart']);

    // Charity
    Route::get('/charity/donations', [CharityController::class, 'myDonations']);
    Route::post('/charity/donate/voucher', [CharityController::class, 'donateVoucher']);
    Route::post('/charity/donate/product', [CharityController::class, 'donateProduct']);
    Route::get('/charity/impact', [CharityController::class, 'impact']);

    // Stats
    Route::get('/my-stats/consumption', [ProfileController::class, 'consumptionReport']);
    Route::get('/my-stats/favorite-products', [ProfileController::class, 'favoriteProducts']);
    Route::get('/my-stats/monthly', [ProfileController::class, 'monthlyStats']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Suggestions
    Route::post('/suggestions', [SuggestionController::class, 'store']);

    // Delegate Shopping
    Route::post('/delegate-shopping', [App\Http\Controllers\DelegateShoppingController::class, 'store']);
    Route::get('/delegate-shopping', [App\Http\Controllers\DelegateShoppingController::class, 'index']);
    Route::get('/delegate-shopping/{id}', [App\Http\Controllers\DelegateShoppingController::class, 'show']);

    // Selective Subscriptions
    Route::prefix('selective-subscriptions')->group(function () {
        Route::get('/', [SelectiveSubscriptionController::class, 'index']);
        Route::post('/', [SelectiveSubscriptionController::class, 'store']);
        Route::get('/{id}', [SelectiveSubscriptionController::class, 'show']);
        Route::put('/{id}', [SelectiveSubscriptionController::class, 'update']);
        Route::post('/{id}/items', [SelectiveSubscriptionController::class, 'addItem']);
        Route::put('/{id}/items/{itemId}', [SelectiveSubscriptionController::class, 'updateItem']);
        Route::patch('/{id}/items/{itemId}/toggle', [SelectiveSubscriptionController::class, 'toggleItem']);
        Route::delete('/{id}/items/{itemId}', [SelectiveSubscriptionController::class, 'removeItem']);
        Route::put('/{id}/sync-items', [SelectiveSubscriptionController::class, 'syncItems']);
        Route::post('/{id}/suspend', [SelectiveSubscriptionController::class, 'suspend']);
        Route::post('/{id}/resume', [SelectiveSubscriptionController::class, 'resume']);
        Route::delete('/{id}', [SelectiveSubscriptionController::class, 'cancel']);
        Route::get('/{id}/history', [SelectiveSubscriptionController::class, 'history']);
    });
});

/*
|--------------------------------------------------------------------------
| Routes PUBLIQUES — Nos Conseils
|--------------------------------------------------------------------------
*/
Route::prefix('conseils')->name('conseils.')->group(function () {

    // Stats des catégories (pour les onglets)
    Route::get('categories/stats', [PublicConseilController::class, 'categoryStats'])
         ->name('categories.stats');

    // Liste paginée
    Route::get('/', [PublicConseilController::class, 'index'])
         ->name('index');

    // Détail par slug
    Route::get('{slug}', [PublicConseilController::class, 'show'])
         ->name('show');

    // Like
    Route::post('{conseil}/like', [PublicConseilController::class, 'like'])
         ->name('like');
});

/*
|--------------------------------------------------------------------------
| Routes ADMIN — Nos Conseils (middleware auth + admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin/conseils')
     ->name('admin.conseils.')
     ->middleware(['auth:sanctum', 'role:admin'])
     ->group(function () {

    Route::get('/',    [AdminConseilController::class, 'index'])->name('index');
    Route::post('/',   [AdminConseilController::class, 'store'])->name('store');
    Route::get('{conseil}',    [AdminConseilController::class, 'show'])->name('show');
    Route::put('{conseil}',    [AdminConseilController::class, 'update'])->name('update');
    Route::patch('{conseil}',  [AdminConseilController::class, 'update'])->name('update.patch');
    Route::delete('{conseil}', [AdminConseilController::class, 'destroy'])->name('destroy');

    // Actions rapides
    Route::patch('{conseil}/toggle-publish',  [AdminConseilController::class, 'togglePublish'])
         ->name('toggle-publish');
    Route::patch('{conseil}/toggle-featured', [AdminConseilController::class, 'toggleFeatured'])
         ->name('toggle-featured');

    // Upload médias
    Route::post('upload-media',  [AdminConseilController::class, 'uploadMedia'])->name('upload-media');
    Route::delete('delete-media',[AdminConseilController::class, 'deleteMedia'])->name('delete-media');
});

// ========================
// PREPARATOR ROUTES
// ========================
Route::middleware(['auth:sanctum', 'role:preparator,admin'])->prefix('preparator')->group(function () {
    Route::get('/orders', [AdminOrderController::class, 'preparatorOrders']);
    Route::post('/orders/{id}/prepare', [AdminOrderController::class, 'startPreparation']);
    Route::post('/orders/{id}/ready', [AdminOrderController::class, 'markReady']);
    Route::put('/orders/{id}/items/{itemId}/status', [AdminOrderController::class, 'updateItemStatus']);
});

// ========================
// ADMIN ROUTES
// ========================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/kpis', [DashboardController::class, 'kpis']);
    Route::get('/dashboard/alerts', [DashboardController::class, 'alerts']);

    // Statistics
    Route::get('/stats/sales', [AdminStatsController::class, 'sales']);
    Route::get('/stats/orders', [AdminStatsController::class, 'orders']);
    Route::get('/stats/products', [AdminStatsController::class, 'products']);
    Route::get('/stats/users', [AdminStatsController::class, 'users']);
    Route::get('/stats/games', [AdminStatsController::class, 'games']);
    Route::get('/stats/charity', [AdminStatsController::class, 'charity']);
    Route::get('/stats/revenue', [AdminStatsController::class, 'revenue']);
    Route::get('/stats/export', [AdminStatsController::class, 'export']);

    // Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
    Route::post('/users/{id}/ban', [AdminUserController::class, 'ban']);
    Route::post('/users/{id}/unban', [AdminUserController::class, 'unban']);
    Route::put('/users/{id}/role', [AdminUserController::class, 'updateRole']);
    Route::get('/users/{id}/orders', [AdminUserController::class, 'userOrders']);
    Route::post('/users/{id}/loyalty/add', [AdminUserController::class, 'addLoyaltyPoints']);

    // Categories (Rayons)
    Route::prefix('categories')->group(function () {
        Route::get('/tree', [AdminCategoryController::class, 'tree']);
        Route::get('/', [AdminCategoryController::class, 'index']);
        Route::post('/', [AdminCategoryController::class, 'store']);
        Route::get('/{id}', [AdminCategoryController::class, 'show']);
        Route::put('/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/{id}', [AdminCategoryController::class, 'destroy']);
        Route::post('/{id}/toggle', [AdminCategoryController::class, 'toggle']);
        Route::put('/{id}/reorder', [AdminCategoryController::class, 'reorder']);
    });

    // Product Categories — ordre important : routes statiques avant {id}
    Route::prefix('product-categories')->group(function () {
        Route::get('/grouped', [AdminCategoryController::class, 'grouped']);
        Route::get('/by-rayon/{categoryId}', [AdminCategoryController::class, 'byRayon']);
        Route::get('/', [AdminCategoryController::class, 'productCategories']);
        Route::post('/', [AdminCategoryController::class, 'storeProductCategory']);
        Route::put('/{id}', [AdminCategoryController::class, 'updateProductCategory']);
        Route::delete('/{id}', [AdminCategoryController::class, 'destroyProductCategory']);
        Route::post('/{id}/toggle', [AdminCategoryController::class, 'toggleProductCategory']);
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/low-stock', [AdminProductController::class, 'lowStock']);
        Route::get('/out-of-stock', [AdminProductController::class, 'outOfStock']);
        Route::get('/export', [AdminProductController::class, 'export']);
        Route::post('/import', [AdminProductController::class, 'import']);
        Route::get('/', [AdminProductController::class, 'index']);
        Route::post('/', [AdminProductController::class, 'store']);
        Route::get('/{id}', [AdminProductController::class, 'show']);
        Route::put('/{id}', [AdminProductController::class, 'update']);
        Route::delete('/{id}', [AdminProductController::class, 'destroy']);
        Route::post('/{id}/toggle', [AdminProductController::class, 'toggle']);
        Route::post('/{id}/duplicate', [AdminProductController::class, 'duplicate']);
        Route::post('/{id}/images', [AdminProductController::class, 'uploadImages']);
        Route::delete('/{id}/images/{imageId}', [AdminProductController::class, 'deleteImage']);
        Route::put('/{id}/stock', [AdminProductController::class, 'updateStock']);
        Route::post('/{id}/label', [AdminProductController::class, 'setLabel']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/export', [AdminOrderController::class, 'export']);
        Route::get('/', [AdminOrderController::class, 'index']);
        Route::get('/{id}', [AdminOrderController::class, 'show']);
        Route::put('/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/{id}/assign-driver', [AdminOrderController::class, 'assignDriver']);
        Route::post('/{id}/refund', [AdminOrderController::class, 'refund']);
    });

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/upcoming', [AdminSubscriptionController::class, 'upcoming']);
        Route::get('/', [AdminSubscriptionController::class, 'index']);
        Route::get('/{id}', [AdminSubscriptionController::class, 'show']);
        Route::put('/{id}', [AdminSubscriptionController::class, 'update']);
        Route::post('/{id}/suspend', [AdminSubscriptionController::class, 'suspend']);
        Route::post('/{id}/process', [AdminSubscriptionController::class, 'processManually']);
    });

    // Admin Selective Subscriptions
    Route::prefix('selective-subscriptions')->group(function () {
        Route::get('/upcoming', [AdminSelectiveSubscriptionController::class, 'upcoming']);
        Route::get('/stats', [AdminSelectiveSubscriptionController::class, 'stats']);
        Route::get('/', [AdminSelectiveSubscriptionController::class, 'index']);
        Route::get('/{id}', [AdminSelectiveSubscriptionController::class, 'show']);
        Route::put('/{id}', [AdminSelectiveSubscriptionController::class, 'update']);
        Route::post('/{id}/suspend', [AdminSelectiveSubscriptionController::class, 'suspend']);
        Route::post('/{id}/resume', [AdminSelectiveSubscriptionController::class, 'resume']);
        Route::post('/{id}/process', [AdminSelectiveSubscriptionController::class, 'processManually']);
    });

    // Promotions
    Route::apiResource('/promotions', AdminPromotionController::class);
    Route::post('/promotions/{id}/toggle', [AdminPromotionController::class, 'toggle']);

    // Coupons
    Route::prefix('coupons')->group(function () {
        Route::get('/', [AdminCouponController::class, 'index']);
        Route::post('/', [AdminCouponController::class, 'store']);
        Route::post('/generate-bulk', [AdminCouponController::class, 'generateBulk']);
        Route::get('/{id}', [AdminCouponController::class, 'show']);
        Route::put('/{id}', [AdminCouponController::class, 'update']);
        Route::delete('/{id}', [AdminCouponController::class, 'destroy']);
        Route::get('/{id}/usages', [AdminCouponController::class, 'usages']);
    });

    // ========================
    // GAMES (ADMIN) — Routes complètes
    // ========================
    $gc = AdminGameController::class;

    // Tableau de bord global
    Route::get('games/dashboard', [$gc, 'dashboard']);

    // DÉFIS
    Route::prefix('games/defis')->group(function () use ($gc) {
        Route::get('/',                             [$gc, 'defiIndex']);
        Route::post('/',                            [$gc, 'defiStore']);
        Route::get('/{id}',                         [$gc, 'defiShow']);
        Route::put('/{id}',                         [$gc, 'defiUpdate']);
        Route::delete('/{id}',                      [$gc, 'defiDestroy']);
        Route::patch('/{id}/status',                [$gc, 'defiSetStatus']);
        Route::post('/{id}/select-participants',    [$gc, 'defiSelectParticipants']);
        Route::post('/{id}/award-winner',           [$gc, 'defiAwardWinner']);
    });

    // CARTE À GRATTER
    Route::prefix('games/scratch-cards')->group(function () use ($gc) {
        Route::get('/',                 [$gc, 'scratchIndex']);
        Route::get('/stats',            [$gc, 'scratchStats']);
        Route::post('/trigger-manual',  [$gc, 'scratchTriggerManual']);
        Route::patch('/{id}/claim',     [$gc, 'scratchClaimPrize']);
    });

    // ROUE E-SUP'M
    Route::prefix('games/wheel')->group(function () use ($gc) {
        Route::get('/configs',              [$gc, 'wheelConfigIndex']);
        Route::post('/configs',             [$gc, 'wheelConfigStore']);
        Route::put('/configs/{id}',         [$gc, 'wheelConfigUpdate']);
        Route::get('/spins',                [$gc, 'wheelSpinsIndex']);
        Route::post('/spin-manual',         [$gc, 'wheelSpinManual']);
        Route::patch('/spins/{id}/claim',   [$gc, 'wheelClaimPrize']);
    });

    // QUIZ
    Route::prefix('games/quiz')->group(function () use ($gc) {
        Route::get('/',                                     [$gc, 'quizIndex']);
        Route::post('/',                                    [$gc, 'quizStore']);
        Route::get('/{id}',                                 [$gc, 'quizShow']);
        Route::put('/{id}',                                 [$gc, 'quizUpdate']);
        Route::delete('/{id}',                              [$gc, 'quizDestroy']);
        Route::patch('/{id}/status',                        [$gc, 'quizSetStatus']);
        Route::post('/{id}/questions',                      [$gc, 'quizAddQuestion']);
        Route::put('/{id}/questions/{questionId}',          [$gc, 'quizUpdateQuestion']);
        Route::delete('/{id}/questions/{questionId}',       [$gc, 'quizDeleteQuestion']);
        Route::get('/{id}/participations',                  [$gc, 'quizParticipations']);
    });

    // BATTLE (VOTE)
    Route::prefix('games/battle')->group(function () use ($gc) {
        Route::get('/',                         [$gc, 'battleIndex']);
        Route::post('/',                        [$gc, 'battleStore']);
        Route::get('/{id}',                     [$gc, 'battleShow']);
        Route::put('/{id}',                     [$gc, 'battleUpdate']);
        Route::delete('/{id}',                  [$gc, 'battleDestroy']);
        Route::patch('/{id}/status',            [$gc, 'battleSetStatus']);
        Route::post('/{id}/close',              [$gc, 'battleClose']);
        Route::post('/{id}/candidates',         [$gc, 'battleAddCandidate']);
        Route::get('/{id}/votes',               [$gc, 'battleVotes']);
    });

    // JUSTE PRIX
    Route::prefix('games/juste-prix')->group(function () use ($gc) {
        Route::get('/',                                     [$gc, 'justePrixIndex']);
        Route::post('/',                                    [$gc, 'justePrixStore']);
        Route::get('/{id}',                                 [$gc, 'justePrixShow']);
        Route::put('/{id}',                                 [$gc, 'justePrixUpdate']);
        Route::patch('/{id}/status',                        [$gc, 'justePrixSetStatus']);
        Route::get('/{id}/participations',                  [$gc, 'justePrixParticipations']);
        Route::post('/participations/{participationId}/award', [$gc, 'justePrixAwardWinner']);
    });

    // AUTO-SCHEDULING (appel interne ou webhook cron)
    Route::prefix('games/scheduler')->middleware('throttle:10,1')->group(function () use ($gc) {
        Route::post('/close-expired', [$gc, 'autoCloseExpiredGames']);
        Route::post('/activate-quiz', [$gc, 'autoActivateQuiz']);
        Route::post('/activate-battle', [$gc, 'autoActivateBattle']);
    });

    // Partners
    Route::prefix('partners')->group(function () {
        Route::get('/', [AdminPartnerController::class, 'index']);
        Route::get('/{id}', [AdminPartnerController::class, 'show']);
        Route::put('/{id}', [AdminPartnerController::class, 'update']);
        Route::post('/{id}/approve', [AdminPartnerController::class, 'approve']);
        Route::post('/{id}/reject', [AdminPartnerController::class, 'reject']);
        Route::delete('/{id}', [AdminPartnerController::class, 'destroy']);
    });

    // Advertisements
    Route::prefix('advertisements')->group(function () {
        Route::get('/stats', [AdminAdvertisementController::class, 'stats']);
        Route::get('/', [AdminAdvertisementController::class, 'index']);
        Route::post('/', [AdminAdvertisementController::class, 'store']);
        Route::get('/{id}', [AdminAdvertisementController::class, 'show']);
        Route::put('/{id}', [AdminAdvertisementController::class, 'update']);
        Route::delete('/{id}', [AdminAdvertisementController::class, 'destroy']);
        Route::post('/{id}/toggle', [AdminAdvertisementController::class, 'toggle']);
    });

    // Charity
    Route::prefix('charity')->group(function () {
        Route::get('/dashboard', [AdminCharityController::class, 'dashboard']);
        Route::get('/donations/export', [AdminCharityController::class, 'exportDonations']);
        Route::get('/donations', [AdminCharityController::class, 'donations']);
        Route::get('/donations/{id}', [AdminCharityController::class, 'showDonation']);
        Route::put('/donations/{id}/status', [AdminCharityController::class, 'updateStatus']);
        Route::post('/donations/bulk-update', [AdminCharityController::class, 'bulkUpdateStatus']);
        Route::post('/donations/{id}/scratch-card', [AdminCharityController::class, 'triggerScratchCard']);
        Route::get('/vouchers', [AdminCharityController::class, 'vouchers']);
        Route::post('/vouchers', [AdminCharityController::class, 'createVoucher']);
        Route::post('/vouchers/{code}/use', [AdminCharityController::class, 'useVoucher']);
    });

    // Recipes
    Route::apiResource('/recipes', AdminRecipeController::class);
    Route::post('/recipes/{id}/publish', [AdminRecipeController::class, 'publish']);

    // Suggestions
    Route::prefix('suggestions')->group(function () {
        Route::get('/', [SuggestionController::class, 'adminIndex']);
        Route::put('/{id}/status', [SuggestionController::class, 'updateStatus']);
        Route::post('/{id}/respond', [SuggestionController::class, 'respond']);
    });

    // Deliveries
    Route::prefix('deliveries')->group(function () {
        Route::get('/drivers/available', [AdminDeliveryController::class, 'availableDrivers']);
        Route::get('/', [AdminDeliveryController::class, 'index']);
        Route::get('/{id}', [AdminDeliveryController::class, 'show']);
        Route::put('/{id}/status', [AdminDeliveryController::class, 'updateStatus']);
        Route::post('/{id}/assign', [AdminDeliveryController::class, 'assignDriver']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::post('/send', [NotificationController::class, 'adminSend']);
        Route::post('/broadcast', [NotificationController::class, 'broadcast']);
    });

    // Delegate Shopping
    Route::prefix('delegate-shopping')->group(function () {
        Route::get('/', [App\Http\Controllers\DelegateShoppingController::class, 'adminIndex']);
        Route::put('/{id}/status', [App\Http\Controllers\DelegateShoppingController::class, 'updateStatus']);
    });

    // Badges
    Route::apiResource('/badges', App\Http\Controllers\Admin\AdminBadgeController::class);
    Route::post('/badges/{id}/award/{userId}', [App\Http\Controllers\Admin\AdminBadgeController::class, 'award']);

    // Newsletter
    Route::prefix('newsletter')->group(function () {
        Route::get('/subscribers', [App\Http\Controllers\Admin\AdminNewsletterController::class, 'index']);
        Route::post('/send', [App\Http\Controllers\Admin\AdminNewsletterController::class, 'send']);
    });

    // Food Boxes
    Route::prefix('food-boxes')->group(function () {
        Route::get('/products/search', [AdminFoodBoxController::class, 'searchProducts']);
        Route::get('/', [AdminFoodBoxController::class, 'index']);
        Route::post('/', [AdminFoodBoxController::class, 'store']);
        Route::get('/{id}', [AdminFoodBoxController::class, 'show']);
        Route::put('/{id}', [AdminFoodBoxController::class, 'update']);
        Route::delete('/{id}', [AdminFoodBoxController::class, 'destroy']);
        Route::post('/{id}/toggle', [AdminFoodBoxController::class, 'toggle']);
        Route::post('/{id}/duplicate', [AdminFoodBoxController::class, 'duplicate']);
        Route::post('/{id}/items', [AdminFoodBoxController::class, 'addItem']);
        Route::delete('/{id}/items/{itemId}', [AdminFoodBoxController::class, 'removeItem']);
    });
});

// ========================
// PAYMENT WEBHOOKS (no auth)
// ========================
Route::prefix('webhooks')->group(function () {
    Route::post('/cinetpay', [App\Http\Controllers\PaymentController::class, 'cinetpayWebhook']);
    Route::post('/paydunya', [App\Http\Controllers\PaymentController::class, 'paydunyaWebhook']);
});