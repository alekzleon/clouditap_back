<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminCouponController;
use App\Http\Controllers\Api\V1\AdminCourtesyController;
use App\Http\Controllers\Api\V1\AdminPromotionController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CardController;
use App\Http\Controllers\Api\V1\CardCreditController;
use App\Http\Controllers\Api\V1\CardDesignController;
use App\Http\Controllers\Api\V1\CardOrderController;
use App\Http\Controllers\Api\V1\CloudiTapLandingController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\LinkPageController;
use App\Http\Controllers\Api\V1\MediaAssetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Models\LinkPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/ping', function () {
        return response()->json([
            'data' => [
                'app' => config('app.name'),
                'environment' => app()->environment(),
            ],
            'message' => 'pong',
            'status' => 200,
        ]);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::get('/public/link-pages/{linkPage}', [LinkPageController::class, 'publicShow']);
    Route::post('/public/link-pages/{linkPage}/events', [AnalyticsController::class, 'storePublicEvent']);
    Route::get('/public/cards/{card}/link-page', [LinkPageController::class, 'publicShowByCard']);
    Route::post('/public/cards/{card}/events', [AnalyticsController::class, 'storePublicCardEvent']);
    Route::get('/public/clouditap', [CloudiTapLandingController::class, 'public']);
    Route::get('/media/{media}', [MediaAssetController::class, 'show'])->whereNumber('media');
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
    Route::get('/public/link-page/{slug}', function (Request $request, string $slug) {
        $linkPage = LinkPage::where('slug', $slug)->firstOrFail();

        return app(LinkPageController::class)->publicShow($request, $linkPage);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('admin')->middleware('role:admin|superadmin')->group(function (): void {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/users/{user}', [AdminController::class, 'user']);
            Route::get('/orders', [AdminController::class, 'orders']);
            Route::get('/orders/{order}', [AdminController::class, 'order']);
            Route::get('/courtesies', [AdminCourtesyController::class, 'index']);
            Route::post('/courtesies', [AdminCourtesyController::class, 'store']);
            Route::apiResource('coupons', AdminCouponController::class);
            Route::apiResource('promotions', AdminPromotionController::class);
            Route::get('/prints', [AdminController::class, 'printOrders']);
            Route::get('/prints/{printJob}', [AdminController::class, 'printOrder']);
            Route::get('/prints/{printJob}/order-info', [AdminController::class, 'printOrderInfo']);
            Route::get('/prints/{printJob}/status-logs', [AdminController::class, 'printOrderStatusLogs']);
            Route::patch('/prints/{printJob}/status', [AdminController::class, 'updatePrintOrderStatus']);
            Route::post('/prints/{printJob}/reopen', [AdminController::class, 'reopenPrintOrder']);
            Route::get('/prints/{printJob}/download/{side}', [AdminController::class, 'downloadPrintSide']);
            Route::get('/print-queue', [AdminController::class, 'printQueue']);
            Route::get('/cards/{card}/print', [AdminController::class, 'printCard']);
            Route::get('/cards/{card}/status-logs', [AdminController::class, 'cardStatusLogs']);
            Route::patch('/cards/{card}/status', [AdminController::class, 'updateCardStatus']);

            Route::prefix('clouditap')->group(function (): void {
                Route::get('/', [CloudiTapLandingController::class, 'admin']);
                Route::post('/banner', [CloudiTapLandingController::class, 'storeHero']);
                Route::put('/banner', [CloudiTapLandingController::class, 'updateHero']);
                Route::put('/banner/{hero}', [CloudiTapLandingController::class, 'updateHero']);
                Route::delete('/banner/{hero}', [CloudiTapLandingController::class, 'destroyHero']);
                Route::post('/designs', [CloudiTapLandingController::class, 'storeDesign']);
                Route::put('/designs/{design}', [CloudiTapLandingController::class, 'updateDesign']);
                Route::delete('/designs/{design}', [CloudiTapLandingController::class, 'destroyDesign']);
                Route::post('/pricing', [CloudiTapLandingController::class, 'storePricing']);
                Route::put('/pricing/{plan}', [CloudiTapLandingController::class, 'updatePricing']);
                Route::delete('/pricing/{plan}', [CloudiTapLandingController::class, 'destroyPricing']);
                Route::post('/reviews', [CloudiTapLandingController::class, 'storeReview']);
                Route::put('/reviews/{review}', [CloudiTapLandingController::class, 'updateReview']);
                Route::delete('/reviews/{review}', [CloudiTapLandingController::class, 'destroyReview']);
                Route::post('/faqs', [CloudiTapLandingController::class, 'storeFaq']);
                Route::put('/faqs/{faq}', [CloudiTapLandingController::class, 'updateFaq']);
                Route::delete('/faqs/{faq}', [CloudiTapLandingController::class, 'destroyFaq']);
                Route::put('/settings', [CloudiTapLandingController::class, 'updateSettings']);
            });
        });

        Route::get('/card-credits', [CardCreditController::class, 'show']);
        Route::post('/card-orders', [CardOrderController::class, 'store']);
        Route::post('/card-orders/{order}/sync-payment', [CardOrderController::class, 'syncPayment']);
        Route::post('/card-orders/{order}/simulate-payment', [CardOrderController::class, 'simulatePayment']);
        Route::post('/coupons/validate-card-order', [CouponController::class, 'validateForCardOrder']);

        Route::prefix('profile')->group(function (): void {
            Route::get('/', [ProfileController::class, 'show']);
            Route::patch('/name', [ProfileController::class, 'updateName']);
            Route::patch('/public-slug', [ProfileController::class, 'updatePublicSlug']);
            Route::patch('/password', [ProfileController::class, 'updatePassword']);
            Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
            Route::post('/referral-code', [ProfileController::class, 'generateReferralCode']);
            Route::put('/shipping-address', [ProfileController::class, 'updateShippingAddress']);
        });

        Route::prefix('analytics')->group(function (): void {
            Route::get('/cards', [AnalyticsController::class, 'cards']);
            Route::get('/summary', [AnalyticsController::class, 'summary']);
            Route::get('/timeseries', [AnalyticsController::class, 'timeseries']);
            Route::get('/timeseries-by-card', [AnalyticsController::class, 'timeseriesByCard']);
            Route::get('/traffic-sources', [AnalyticsController::class, 'trafficSources']);
            Route::get('/traffic-sources-by-card', [AnalyticsController::class, 'trafficSourcesByCard']);
            Route::get('/top-links', [AnalyticsController::class, 'topLinks']);
            Route::get('/top-links-by-card', [AnalyticsController::class, 'topLinksByCard']);
            Route::get('/top-cards', [AnalyticsController::class, 'topCards']);
            Route::get('/recent-activity', [AnalyticsController::class, 'recentActivity']);
        });

        Route::get('/link-page', [LinkPageController::class, 'legacyShow']);
        Route::put('/link-page', [LinkPageController::class, 'legacyUpdate']);
        Route::post('/link-page/assets', [LinkPageController::class, 'legacyUploadAsset']);

        Route::get('/link-pages', [LinkPageController::class, 'index']);
        Route::post('/link-pages', [LinkPageController::class, 'store']);
        Route::get('/link-pages/{linkPage}', [LinkPageController::class, 'show']);
        Route::put('/link-pages/{linkPage}', [LinkPageController::class, 'update']);
        Route::post('/link-pages/{linkPage}/assets', [LinkPageController::class, 'uploadAsset']);
        Route::delete('/link-pages/{linkPage}/links/{link}', [LinkPageController::class, 'destroyLink']);
        Route::delete('/link-pages/{linkPage}/banners/{banner}', [LinkPageController::class, 'destroyBanner']);
        Route::delete('/link-pages/{linkPage}', [LinkPageController::class, 'destroy']);
        Route::delete('/cards/{card}/link-page', [LinkPageController::class, 'destroyByCard']);

        Route::post('/cards/{card}/print', [CardController::class, 'print']);
        Route::get('/cards/{card}/status-logs', [CardController::class, 'statusLogs']);
        Route::patch('/cards/{card}/name', [CardController::class, 'updateName']);

        Route::get('/cards/{card}/design', [CardDesignController::class, 'show']);
        Route::put('/cards/{card}/design', [CardDesignController::class, 'update']);
        Route::post('/cards/{card}/design/assets', [CardDesignController::class, 'uploadAsset']);
        Route::post('/cards/{card}/design/print-files', [CardDesignController::class, 'uploadPrintFiles']);

        Route::apiResource('cards', CardController::class);
    });
});
