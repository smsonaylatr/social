<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'socialflow:create-admin 
                            {--name= : Admin adı} 
                            {--email= : Admin e-posta adresi} 
                            {--password= : Admin parolası}';

    protected $description = 'SocialFlow AI için yeni bir süper yönetici kullanıcısı oluşturur';

    public function handle(): int
    {
        $this->info('SocialFlow AI — Yönetici Hesabı Oluşturma');

        $name = $this->option('name') ?: $this->ask('Yönetici Adı ve Soyadı', 'Yönetici');
        $email = $this->option('email') ?: $this->ask('E-posta Adresi', 'admin@socialflow.ai');
        $password = $this->option('password') ?: $this->secret('Parola (En az 8 karakter)');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => UserRole::SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        $defaultWorkspace = Workspace::firstOrCreate(
            ['slug' => 'default-workspace'],
            ['name' => 'Ana Çalışma Alanı']
        );

        $defaultWorkspace->users()->syncWithoutDetaching([
            $user->id => ['role' => UserRole::SUPER_ADMIN->value],
        ]);

        $this->info("✓ Süper Yönetici başarıyla oluşturuldu: {$user->email}");
        $this->info('Giriş URL: ' . url('/admin'));

        return self::SUCCESS;
    }
}
