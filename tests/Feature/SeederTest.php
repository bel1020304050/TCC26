<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_o_primeiro_administrador(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class); // rodar duas vezes não duplica

        $admin = User::where('email', 'mary@marymodas.com.br')->sole();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check(config('mary.admin_password'), $admin->password));
    }
}
