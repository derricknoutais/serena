🚀 Laravel SaaS Starter Kit
Laravel 12 · Single-Database Multi-Tenancy · Stancl Tenancy · Inertia · Fortify · Herd

A reusable SaaS boilerplate designed to help you launch new multi-tenant products fast.
Built for clarity, simplicity, and repeatability.

This starter kit uses:

Single Database Multi-Tenancy (tenant data scoped by tenant_id)

stancl/tenancy (domain-based tenant identification)

Inertia.js + Vue for the frontend

Fortify for authentication

Herd for local development

Reusable setup command (saas:setup) to initialize a new SaaS in seconds

You can create unlimited SaaS products by using this repo as a template.

📦 Features
🟦 Multi-Tenancy (Single DB)

All tenants share one database

Data is isolated using tenant_id

Every tenant-bound model uses:

use Stancl\Tenancy\Database\Concerns\BelongsToTenant;


Tenant initialization by domain (e.g. demo.app.test)

🟧 Tenant Bootstrapping

Auto-creates tenant_id on models

Global scope applied automatically

Middleware gated tenant routes:

InitializeTenancyByDomain

PreventAccessFromCentralDomains

🟩 Developer Experience

Clean folder structure

Prebuilt seeder + setup command

Inertia-enabled dashboard

Fortify authentication

Ready for Herd (macOS / Windows)

⚡️ Quick Start
1. Create a new project using this template

Click Use this template → Create new repository on GitHub.

Then clone your new repo:

git clone <your-new-repo>
cd <your-new-repo>

2. Install dependencies
composer install


If using Yarn (recommended):

yarn install
yarn dev

3. Setup environment

Copy environment file:

cp .env.example .env
php artisan key:generate


Configure your database in .env.

Make sure the central domain is set:

CENTRAL_DOMAIN=app.test
APP_URL=http://app.test

4. Run migrations
php artisan migrate

5. Run SaaS setup wizard

This creates your first tenant + admin user:

php artisan saas:setup


The wizard asks for:

Tenant name

Tenant ID (slug)

Tenant domain

Admin user info

Example domain:

demo.app.test

6. Add local hosts (Herd or /etc/hosts)

If using Herd, domains auto-resolve.
Otherwise, add manually:

127.0.0.1  app.test
127.0.0.1  demo.app.test

7. Start Development

With Herd — done.
Without Herd:

php artisan serve


Access:

Central app: http://app.test

Tenant app: http://demo.app.test

Login with the admin credentials you created.

🧩 Project Structure
app/
 ├── Models/
 │    ├── Tenant.php
 │    ├── User.php  (uses BelongsToTenant)
 │    └── ...
 ├── Console/
 │    └── Commands/SaasSetup.php
routes/
 ├── web.php        (central + tenant routes separated)
 └── settings.php
database/
 ├── seeders/TenantAndUserSeeder.php
 └── migrations/...

🔐 Authentication

Powered by Laravel Fortify, including:

Login / Logout

Registration (optional)

Email verification

Password resets

Users are tenant-scoped automatically by:

tenant_id


Thanks to BelongsToTenant.

🌐 Routing Structure
Central Routes (landlord)

routes/web.php:

Route::middleware('web')->group(function () {
    Route::get('/', ...)->name('home');
});

Tenant Routes
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/dashboard', ...);
});

🧪 Seeding

To seed demo tenant + user:

php artisan db:seed --class=TenantAndUserSeeder


Or use the interactive setup command:

php artisan saas:setup

🛠 Making the repo your own (Recommended)

If this repo is a template, customize:

APP_NAME in .env.example

Primary color + branding in front-end

Dashboard in resources/js/Pages/Dashboard.vue

Add your business modules (PMS, Rental Fleet, Billing, etc.)

🚧 Roadmap (Optional for your repo)

Planned enhancements:

Filament v4 admin panel (tenant-aware)

Billing + subscription module

Audit log + activity tracking

Tenant impersonation

Modular design for PMS / Car Fleet / Solar Businesses

📄 License

MIT — free to use in all your projects.

🎯 Summary

This starter kit gives you:

A production-grade multi-tenant Laravel project

Reusable for as many SaaS apps as you want

With a clean DX and best practices built-in

It’s perfect for building:

A PMS (Orisha Inn)

A Car Rental SaaS

A Solar business dashboard

Any multi-client SaaS