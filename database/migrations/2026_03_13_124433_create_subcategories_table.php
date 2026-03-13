<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subcategories')) {
            Schema::create('subcategories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('external_id')->unique();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['category_id', 'name']);
                $table->index('name');
                $table->index('updated_at');
            });
        }

        if (Schema::hasColumn('categories', 'parent_id')) {
            $this->migrateCategorySubcategories();

            if ($this->foreignKeyExists('products', 'products_subcategory_id_foreign')) {
                Schema::table('products', function (Blueprint $table): void {
                    $table->dropForeign('products_subcategory_id_foreign');
                });
            }

            DB::table('products as p')
                ->leftJoin('subcategories as s', 'p.subcategory_id', '=', 's.id')
                ->whereNotNull('p.subcategory_id')
                ->whereNull('s.id')
                ->update(['p.subcategory_id' => null]);

            Schema::table('products', function (Blueprint $table): void {
                $table->foreign('subcategory_id')
                    ->references('id')
                    ->on('subcategories')
                    ->cascadeOnDelete();
            });

            Schema::table('categories', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('parent_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subcategories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            }
        });

        $this->restoreCategoryParentsFromSubcategories();

        if ($this->foreignKeyExists('products', 'products_subcategory_id_foreign')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign('products_subcategory_id_foreign');
            });
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('subcategory_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('subcategories');
    }

    protected function migrateCategorySubcategories(): void
    {
        $rows = DB::table('categories')
            ->whereNotNull('parent_id')
            ->get([
                'id',
                'external_id',
                'parent_id',
                'name',
                'slug',
                'is_active',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

        if ($rows->isEmpty()) {
            return;
        }

        DB::table('subcategories')->insert(
            $rows->map(function ($row): array {
                return [
                    'external_id' => $row->external_id,
                    'category_id' => $row->parent_id,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'is_active' => (bool) $row->is_active,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                    'deleted_at' => $row->deleted_at,
                ];
            })->all()
        );

        $categoryExternalIds = DB::table('categories')
            ->pluck('external_id', 'id');
        $subcategoryIds = DB::table('subcategories')
            ->pluck('id', 'external_id');

        DB::table('products')
            ->whereNotNull('subcategory_id')
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($categoryExternalIds, $subcategoryIds): void {
                foreach ($products as $product) {
                    $externalId = $categoryExternalIds[$product->subcategory_id] ?? null;
                    $subcategoryId = $externalId ? ($subcategoryIds[$externalId] ?? null) : null;

                    if ($subcategoryId !== null) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update(['subcategory_id' => $subcategoryId]);
                    }
                }
            });

        DB::table('products')
            ->whereNotNull('subcategory_id')
            ->whereNotIn('subcategory_id', DB::table('subcategories')->select('id'))
            ->update(['subcategory_id' => null]);

        DB::table('categories')->whereNotNull('parent_id')->delete();
    }

    protected function restoreCategoryParentsFromSubcategories(): void
    {
        $rows = DB::table('subcategories')->get([
            'external_id',
            'category_id',
            'name',
            'slug',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        if ($rows->isEmpty()) {
            return;
        }

        $parentExternalIds = DB::table('categories')
            ->pluck('external_id', 'id');

        DB::table('categories')->insert(
            $rows->map(function ($row) use ($parentExternalIds): array {
                return [
                    'external_id' => $row->external_id,
                    'parent_id' => $parentExternalIds[$row->category_id] ?? null,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'is_active' => (bool) $row->is_active,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                    'deleted_at' => $row->deleted_at,
                ];
            })->all()
        );

        $categoryIds = DB::table('categories')
            ->pluck('id', 'external_id');
        $subcategoryIds = DB::table('subcategories')
            ->pluck('external_id', 'id');

        DB::table('products')
            ->whereNotNull('subcategory_id')
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($categoryIds, $subcategoryIds): void {
                foreach ($products as $product) {
                    $externalId = $subcategoryIds[$product->subcategory_id] ?? null;
                    $categoryId = $externalId ? ($categoryIds[$externalId] ?? null) : null;

                    if ($categoryId !== null) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update(['subcategory_id' => $categoryId]);
                    }
                }
            });
    }

    protected function foreignKeyExists(string $table, string $constraint): bool
    {
        $result = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [$table, $constraint]
        );

        return $result !== null;
    }
};
