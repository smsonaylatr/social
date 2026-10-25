<?php

namespace App\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Process;
use Livewire\Component;

class SystemDeployButton extends Component
{
    public bool $isDeploying = false;

    public function deploy()
    {
        $this->isDeploying = true;

        $basePath = base_path();
        $outputLogs = [];

        try {
            // 1. Git Pull
            $gitPull = Process::path($basePath)->run('git pull origin main');
            $pullOutput = trim($gitPull->output() ?: $gitPull->errorOutput());
            $outputLogs[] = "Git: " . (empty($pullOutput) ? 'Başarılı' : $pullOutput);

            // 2. Migration
            $migrate = Process::path($basePath)->run('php artisan migrate --force');
            $migrateOutput = trim($migrate->output() ?: $migrate->errorOutput());
            $outputLogs[] = "Migrate: " . (empty($migrateOutput) ? 'Tamamlandı' : $migrateOutput);

            // 3. Optimize Clear
            $clear = Process::path($basePath)->run('php artisan optimize:clear');
            $outputLogs[] = "Cache: Temizlendi";

            \App\Models\AuditLog::record('system_deploy', null, [
                'details' => implode(' | ', $outputLogs),
            ]);

            Notification::make()
                ->title('✅ Sunucu Başarıyla Güncellendi!')
                ->body(implode(' | ', $outputLogs))
                ->success()
                ->duration(8000)
                ->send();

            // Sayfayı yenile ki yeni bileşenler ve tablolar yüklensin
            $this->js('setTimeout(function() { window.location.reload(); }, 1500);');

        } catch (\Throwable $e) {
            Notification::make()
                ->title('❌ Güncelleme Sırasında Hata Oluştu')
                ->body($e->getMessage())
                ->danger()
                ->duration(10000)
                ->send();
        } finally {
            $this->isDeploying = false;
        }
    }

    public function render()
    {
        return view('livewire.system-deploy-button');
    }
}
