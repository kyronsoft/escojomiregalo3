<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;

Route::prefix('admin')->group(function () {});

// Route::prefix('dashboard')->group(function () {
// 	Route::view('dashboard-02', 'admin.dashboard.dashboard-02')->name('dashboard-02');
// });

Route::prefix('ecommerce')->name('ecommerce.')->group(function () {
	Route::view('product-page',    'admin.apps.ecommerce.product-page')->name('product-page');
	Route::view('list-products',   'admin.apps.ecommerce.list-products')->name('list-products');
	Route::view('payment-details', 'admin.apps.ecommerce.payment-details')->name('payment-details');
	Route::view('order-history',   'admin.apps.ecommerce.order-history')->name('order-history');
	Route::view('invoice-template', 'admin.apps.ecommerce.invoice-template')->name('invoice-template');

	Route::get('cart',  [CartController::class, 'index'])->name('cart.index');
	Route::post('cart', [CartController::class, 'addcart'])->name('cart.add');
	Route::delete('cart', [CartController::class, 'remove'])->name('cart.remove');
	Route::post('finish', [CartController::class, 'finish'])->name('cart.finish');
	Route::get('finish-review', [CartController::class, 'finishReview'])
		->name('cart.finish.review')
		->middleware(['auth', 'role:colaborador']);
	Route::post('finish-update', [CartController::class, 'finishUpdate'])
		->name('cart.finish.update')
		->middleware(['auth', 'role:colaborador']);
	// Asegúrate que el path de la vista coincide con el archivo Blade que creaste:
	Route::get('/checkout', [CartController::class, 'checkout'])
		->name('checkout')
		->middleware(['auth', 'role:colaborador']);

	Route::view('list-wish', 'admin.apps.ecommerce.list-wish')->name('list-wish');
	Route::view('pricing',   'admin.apps.ecommerce.pricing')->name('pricing');
});

Route::prefix('email')->group(function () {
	Route::view('email_inbox', 'admin.apps.email_inbox')->name('email_inbox');
	Route::view('email_read', 'admin.apps.email_read')->name('email_read');
	Route::view('email_compose', 'admin.apps.email_compose')->name('email_compose');
});

Route::prefix('chat')->group(function () {
	Route::view('chat', 'admin.apps.chat')->name('chat');
	Route::view('chat-video', 'admin.apps.chat-video')->name('chat-video');
});

Route::prefix('users')->group(function () {
	Route::view('user-profile', 'admin.apps.user-profile')->name('user-profile');
	Route::view('edit-profile', 'admin.apps.edit-profile')->name('edit-profile');
	Route::view('user-cards', 'admin.apps.user-cards')->name('user-cards');
});

Route::view('bookmark', 'admin.apps.bookmark')->name('bookmark');
Route::view('contacts', 'admin.apps.contacts')->name('contacts');
Route::view('task', 'admin.apps.task')->name('task');
Route::view('calendar-basic', 'admin.apps.calendar-basic')->name('calendar-basic');
Route::view('social-app', 'admin.apps.social-app')->name('social-app');
Route::view('to-do', 'admin.apps.to-do')->name('to-do');
Route::view('search', 'admin.apps.search')->name('search');

Route::view('internationalization', 'admin.pages.internationalization')->name('internationalization');

Route::view('error-page1', 'admin.errors.error-page1')->name('error-page1');
Route::view('error-page2', 'admin.errors.error-page2')->name('error-page2');
Route::view('error-page3', 'admin.errors.error-page3')->name('error-page3');
Route::view('error-page4', 'admin.errors.error-page4')->name('error-page4');

// Route::view('login', 'admin.authentication.login')->name('login');
Route::view('login_one', 'admin.authentication.login_one')->name('login_one');
Route::view('login_two', 'admin.authentication.login_two')->name('login_two');
Route::view('login-bs-validation', 'admin.authentication.login-bs-validation')->name('login-bs-validation');
Route::view('login-bs-tt-validation', 'admin.authentication.login-bs-tt-validation')->name('login-bs-tt-validation');
Route::view('login-sa-validation', 'admin.authentication.login-sa-validation')->name('login-sa-validation');
Route::view('sign-up', 'admin.authentication.sign-up')->name('sign-up');
Route::view('sign-up-one', 'admin.authentication.sign-up-one')->name('sign-up-one');
Route::view('sign-up-two', 'admin.authentication.sign-up-two')->name('sign-up-two');
Route::view('unlock', 'admin.authentication.unlock')->name('unlock');
Route::view('forget-password', 'admin.authentication.forget-password')->name('forget-password');
Route::view('creat-password', 'admin.authentication.creat-password')->name('creat-password');
Route::view('maintenance', 'admin.authentication.maintenance')->name('maintenance');

Route::view('comingsoon', 'admin.comingsoon.comingsoon')->name('comingsoon');
Route::view('comingsoon-bg-video', 'admin.comingsoon.comingsoon-bg-video')->name('comingsoon-bg-video');
Route::view('comingsoon-bg-img', 'admin.comingsoon.comingsoon-bg-img')->name('comingsoon-bg-img');

Route::view('basic-template', 'admin.email.basic-template')->name('basic-template');
Route::view('email-header', 'admin.email.email-header')->name('email-header');
Route::view('template-email', 'admin.email.template-email')->name('template-email');
Route::view('template-email-2', 'admin.email.template-email-2')->name('template-email-2');
Route::view('ecommerce-templates', 'admin.email.ecommerce-templates')->name('ecommerce-templates');
Route::view('email-order-success', 'admin.email.email-order-success')->name('email-order-success');
