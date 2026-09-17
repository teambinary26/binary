<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Role::ensureSk();
    }

    public function down(): void
    {
        $role = Role::query()->where('slug', 'sk')->first();

        if (! $role || $role->users()->exists()) {
            return;
        }

        $role->permissions()->detach();
        $role->delete();
    }
};
