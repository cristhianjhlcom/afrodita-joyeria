<x-layouts::app :title="__('Admin Dashboard')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Main-store synced administration area.
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <p class="text-sm">Catalog, inventory, and orders are synced from the main store.</p>
        </div>
    </div>
</x-layouts::app>
