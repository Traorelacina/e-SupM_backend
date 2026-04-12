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

/*
|--------------------------------------------------------------------------
| API Routes - e-Sup'M Backend
|--------------------------------------------------------------------------
|
| Les routes dans ce fichier sont automatiquement dans le groupe middleware
| "api" qui n'inclut PAS VerifyCsrfToken — pas besoin de token CSRF ici.
| Utilisez uniquement des tokens Bearer (Sanctum) pour l'authentification.
|
*/

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

    // Social Auth
    Route::get('/social/{provider}', [SocialAuthController::class, 'redirect']);
    Route::get('/social/{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('/social/token', [SocialAuthController::class, 'loginWithToken']);

    // 2FA
    Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor']);
});

// ========================
// PUBLIC ROUTES
// ========================
// Categories & Rayons
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/tree', [CategoryController::class, 'tree']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/products', [CategoryController::class, 'products']);

// Products
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);
Route::get('/products/bestsellers', [ProductController::class, 'bestsellers']);
Route::get('/products/premium', [ProductController::class, 'premium']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/products/{id}/related', [ProductController::class, 'related']);

// Search
Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
Route::post('/search/visual', [SearchController::class, 'visualSearch']);

// Promotions (public)
Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/promotions/flash', [PromotionController::class, 'flash']);
Route::get('/promotions/soldes', [PromotionController::class, 'soldes']);
Route::get('/promotions/destockage', [PromotionController::class, 'destockage']);

// Recipes (public)
Route::get('/recipes', [RecipeController::class, 'index']);
Route::get('/recipes/{id}', [RecipeController::class, 'show']);

// Partners (public)
Route::get('/partners', [PartnerController::class, 'index']);
Route::get('/partners/{id}', [PartnerController::class, 'show']);
Route::post('/partners/apply', [PartnerController::class, 'apply']);

// Advertisements (public)
Route::get('/advertisements', [AdvertisementController::class, 'index']);
Route::get('/advertisements/{id}/click', [AdvertisementController::class, 'registerClick']);

// Games (public info)
Route::get('/games', [GameController::class, 'index']);
Route::get('/games/winners', [GameController::class, 'winners']);
Route::get('/games/{id}', [GameController::class, 'show']);

// Reviews (public)
Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

// Charity (public)
Route::get('/charity/vouchers/check/{code}', [CharityController::class, 'checkVoucher']);

// Cart (guest - session based)
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

    // Auth management
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

    // Addresses
    Route::get('/addresses', [ProfileController::class, 'addresses']);
    Route::post('/addresses', [ProfileController::class, 'storeAddress']);
    Route::put('/addresses/{id}', [ProfileController::class, 'updateAddress']);
    Route::delete('/addresses/{id}', [ProfileController::class, 'deleteAddress']);
    Route::put('/addresses/{id}/default', [ProfileController::class, 'setDefaultAddress']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/reorder', [OrderController::class, 'reorder']);

    // Delivery tracking
    Route::get('/deliveries/{orderId}/track', [DeliveryController::class, 'track']);

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
    Route::put('/subscriptions/{id}', [SubscriptionController::class, 'update']);
    Route::post('/subscriptions/{id}/suspend', [SubscriptionController::class, 'suspend']);
    Route::post('/subscriptions/{id}/resume', [SubscriptionController::class, 'resume']);
    Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'cancel']);
    Route::get('/subscriptions/{id}/history', [SubscriptionController::class, 'history']);

    // Loyalty
    Route::get('/loyalty', [LoyaltyController::class, 'dashboard']);
    Route::get('/loyalty/transactions', [LoyaltyController::class, 'transactions']);
    Route::get('/loyalty/badges', [LoyaltyController::class, 'badges']);
    Route::post('/loyalty/redeem', [LoyaltyController::class, 'redeem']);
    Route::get('/loyalty/leaderboard', [LoyaltyController::class, 'leaderboard']);

    // Games (authenticated)
    Route::post('/games/{id}/register', [GameController::class, 'register']);
    Route::post('/games/scratch-card/reveal', [GameController::class, 'revealScratchCard']);
    Route::post('/games/wheel/spin', [GameController::class, 'spinWheel']);
    Route::post('/games/quiz/answer', [GameController::class, 'answerQuiz']);
    Route::post('/games/juste-prix/guess', [GameController::class, 'guessPrix']);
    Route::post('/games/{id}/vote', [GameController::class, 'vote']);
    Route::get('/games/my-participations', [GameController::class, 'myParticipations']);

    // Reviews (authenticated)
    Route::post('/products/{id}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/add', [WishlistController::class, 'add']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'remove']);
    Route::post('/wishlist/to-cart', [WishlistController::class, 'moveToCart']);

    // Charity (authenticated)
    Route::get('/charity/donations', [CharityController::class, 'myDonations']);
    Route::post('/charity/donate/voucher', [CharityController::class, 'donateVoucher']);
    Route::post('/charity/donate/product', [CharityController::class, 'donateProduct']);
    Route::get('/charity/impact', [CharityController::class, 'impact']);

    // My stats
    Route::get('/my-stats/consumption', [ProfileController::class, 'consumptionReport']);
    Route::get('/my-stats/favorite-products', [ProfileController::class, 'favoriteProducts']);
    Route::get('/my-stats/monthly', [ProfileController::class, 'monthlyStats']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Suggestions
    Route::post('/suggestions', [SuggestionController::class, 'store']);

    // Delegate shopping
    Route::post('/delegate-shopping', [\App\Http\Controllers\DelegateShoppingController::class, 'store']);
    Route::get('/delegate-shopping', [\App\Http\Controllers\DelegateShoppingController::class, 'index']);
    Route::get('/delegate-shopping/{id}', [\App\Http\Controllers\DelegateShoppingController::class, 'show']);
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

    // Categories
   


     Route::get('categories/tree', [AdminCategoryController::class, 'tree']);
    Route::post('categories/{id}/toggle', [AdminCategoryController::class, 'toggle']);
    Route::put('categories/{id}/reorder', [AdminCategoryController::class, 'reorder']);

    // Resource en dernier
    Route::apiResource('categories', AdminCategoryController::class);

    // Products
    Route::get('/products/low-stock', [AdminProductController::class, 'lowStock']);
    Route::get('/products/out-of-stock', [AdminProductController::class, 'outOfStock']);
    Route::get('/products/export', [AdminProductController::class, 'export']);
    Route::post('/products/import', [AdminProductController::class, 'import']);
    Route::apiResource('/products', AdminProductController::class);
    Route::post('/products/{id}/toggle', [AdminProductController::class, 'toggle']);
    Route::post('/products/{id}/duplicate', [AdminProductController::class, 'duplicate']);
    Route::post('/products/{id}/images', [AdminProductController::class, 'uploadImages']);
    Route::delete('/products/{id}/images/{imageId}', [AdminProductController::class, 'deleteImage']);
    Route::put('/products/{id}/stock', [AdminProductController::class, 'updateStock']);
    Route::post('/products/{id}/label', [AdminProductController::class, 'setLabel']);

    // Orders
    Route::get('/orders/export', [AdminOrderController::class, 'export']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/assign-driver', [AdminOrderController::class, 'assignDriver']);
    Route::post('/orders/{id}/refund', [AdminOrderController::class, 'refund']);

    // Subscriptions
    Route::get('/subscriptions/upcoming', [AdminSubscriptionController::class, 'upcoming']);
    Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
    Route::get('/subscriptions/{id}', [AdminSubscriptionController::class, 'show']);
    Route::put('/subscriptions/{id}', [AdminSubscriptionController::class, 'update']);
    Route::post('/subscriptions/{id}/suspend', [AdminSubscriptionController::class, 'suspend']);
    Route::post('/subscriptions/{id}/process', [AdminSubscriptionController::class, 'processManually']);

    // Promotions
    Route::apiResource('/promotions', AdminPromotionController::class);
    Route::post('/promotions/{id}/toggle', [AdminPromotionController::class, 'toggle']);

    // Coupons
    Route::apiResource('/coupons', AdminCouponController::class);
    Route::post('/coupons/generate-bulk', [AdminCouponController::class, 'generateBulk']);
    Route::get('/coupons/{id}/usages', [AdminCouponController::class, 'usages']);

    // Games
    Route::get('/games', [AdminGameController::class, 'index']);
    Route::post('/games', [AdminGameController::class, 'store']);
    Route::get('/games/{id}', [AdminGameController::class, 'show']);
    Route::put('/games/{id}', [AdminGameController::class, 'update']);
    Route::delete('/games/{id}', [AdminGameController::class, 'destroy']);
    Route::post('/games/{id}/activate', [AdminGameController::class, 'activate']);
    Route::post('/games/{id}/close', [AdminGameController::class, 'close']);
    Route::get('/games/{id}/participants', [AdminGameController::class, 'participants']);
    Route::post('/games/{id}/select-winners', [AdminGameController::class, 'selectWinners']);
    Route::post('/games/{id}/award', [AdminGameController::class, 'awardWinner']);

    // Partners
    Route::get('/partners', [AdminPartnerController::class, 'index']);
    Route::get('/partners/{id}', [AdminPartnerController::class, 'show']);
    Route::put('/partners/{id}', [AdminPartnerController::class, 'update']);
    Route::post('/partners/{id}/approve', [AdminPartnerController::class, 'approve']);
    Route::post('/partners/{id}/reject', [AdminPartnerController::class, 'reject']);
    Route::delete('/partners/{id}', [AdminPartnerController::class, 'destroy']);

    // Advertisements
    Route::get('/advertisements/stats', [AdminAdvertisementController::class, 'stats']);
    Route::apiResource('/advertisements', AdminAdvertisementController::class);
    Route::post('/advertisements/{id}/toggle', [AdminAdvertisementController::class, 'toggle']);

    // Charity
    Route::get('/charity/donations', [AdminCharityController::class, 'donations']);
    Route::get('/charity/vouchers', [AdminCharityController::class, 'vouchers']);
    Route::put('/charity/donations/{id}/status', [AdminCharityController::class, 'updateStatus']);
    Route::get('/charity/impact', [AdminCharityController::class, 'impact']);

    // Recipes
    Route::apiResource('/recipes', AdminRecipeController::class);
    Route::post('/recipes/{id}/publish', [AdminRecipeController::class, 'publish']);

    // Suggestions
    Route::get('/suggestions', [SuggestionController::class, 'adminIndex']);
    Route::put('/suggestions/{id}/status', [SuggestionController::class, 'updateStatus']);
    Route::post('/suggestions/{id}/respond', [SuggestionController::class, 'respond']);

    // Deliveries
    Route::get('/drivers/available', [AdminDeliveryController::class, 'availableDrivers']);
    Route::get('/deliveries', [AdminDeliveryController::class, 'index']);
    Route::get('/deliveries/{id}', [AdminDeliveryController::class, 'show']);
    Route::put('/deliveries/{id}/status', [AdminDeliveryController::class, 'updateStatus']);
    Route::post('/deliveries/{id}/assign', [AdminDeliveryController::class, 'assignDriver']);

    // Notifications management
    Route::post('/notifications/send', [NotificationController::class, 'adminSend']);
    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast']);

    // Delegate shopping requests
    Route::get('/delegate-shopping', [\App\Http\Controllers\DelegateShoppingController::class, 'adminIndex']);
    Route::put('/delegate-shopping/{id}/status', [\App\Http\Controllers\DelegateShoppingController::class, 'updateStatus']);

    // Badges
    Route::apiResource('/badges', \App\Http\Controllers\Admin\AdminBadgeController::class);
    Route::post('/badges/{id}/award/{userId}', [\App\Http\Controllers\Admin\AdminBadgeController::class, 'award']);

    // Newsletters
    Route::get('/newsletter/subscribers', [\App\Http\Controllers\Admin\AdminNewsletterController::class, 'index']);
    Route::post('/newsletter/send', [\App\Http\Controllers\Admin\AdminNewsletterController::class, 'send']);
});

// ========================
// PAYMENT WEBHOOKS (no auth)
// ========================
Route::prefix('webhooks')->group(function () {
    Route::post('/cinetpay', [\App\Http\Controllers\PaymentController::class, 'cinetpayWebhook']);
    Route::post('/paydunya', [\App\Http\Controllers\PaymentController::class, 'paydunyaWebhook']);
});