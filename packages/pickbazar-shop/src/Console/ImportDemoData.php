<?php

namespace Pickbazar\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Pickbazar\ShopServiceProvider;
use PickBazar\Database\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use PickBazar\Enums\Permission as UserPermission;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class ImportDemoData extends Command
{
    protected $signature = 'pickbazar:seed';

    protected $description = 'Import Demo Data';

    public function handle()
    {

        $this->info('Copying necessary files for seeding....');

        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/sql', public_path('sql'));

        $this->info('File copying successful');

        $this->info('Seeding....');

        $this->seedDemoData();
    }
    public function seedDemoData()
    {
        $media_path = public_path('sql/media.sql');
        $media_sql = file_get_contents($media_path);
        DB::statement($media_sql);

        $attachments_path = public_path('sql/attachments.sql');
        $attachments_sql = file_get_contents($attachments_path);
        DB::statement($attachments_sql);

        $types_path = public_path('sql/types.sql');
        $types_sql = file_get_contents($types_path);
        DB::statement($types_sql);

        $attributes_path = public_path('sql/attributes.sql');
        $attributes_sql = file_get_contents($attributes_path);
        DB::statement($attributes_sql);

        $attribute_values_path = public_path('sql/attribute_values.sql');
        $attribute_values_sql = file_get_contents($attribute_values_path);
        DB::statement($attribute_values_sql);

        $categories_path = public_path('sql/categories.sql');
        $categories_sql = file_get_contents($categories_path);
        DB::statement($categories_sql);

        $products_path = public_path('sql/products.sql');
        $products_sql = file_get_contents($products_path);
        DB::statement($products_sql);

        $attribute_product_path = public_path('sql/attribute_product.sql');
        $attribute_product_sql = file_get_contents($attribute_product_path);
        DB::statement($attribute_product_sql);

        $variation_options_path = public_path('sql/variation_options.sql');
        $variation_options_sql = file_get_contents($variation_options_path);
        DB::statement($variation_options_sql);

        $coupons_path = public_path('sql/coupons.sql');
        $coupons_sql = file_get_contents($coupons_path);
        DB::statement($coupons_sql);

        $orders_status_path = public_path('sql/order_status.sql');
        $orders_status_sql = file_get_contents($orders_status_path);
        DB::statement($orders_status_sql);

        $category_product_path = public_path('sql/category_product.sql');
        $category_product_sql = file_get_contents($category_product_path);
        DB::statement($category_product_sql);

        $settings_path = public_path('sql/settings.sql');
        $settings_sql = file_get_contents($settings_path);
        DB::statement($settings_sql);

        $permissions_path = public_path('sql/permissions.sql');
        $permissions_sql = file_get_contents($permissions_path);
        DB::statement($permissions_sql);

        $shipping_classes_path = public_path('sql/shipping_classes.sql');
        $shipping_classes_sql = file_get_contents($shipping_classes_path);
        DB::statement($shipping_classes_sql);

        $tax_classes_path = public_path('sql/tax_classes.sql');
        $tax_classes_sql = file_get_contents($tax_classes_path);
        DB::statement($tax_classes_sql);

        $this->info('Seed completed successfully!');
    }
}
