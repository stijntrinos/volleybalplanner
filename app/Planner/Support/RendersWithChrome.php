<?php

namespace App\Planner\Support;

use App\Planner\Models\PlannerSetting;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * What an image from Chrome needs: Browsershot with the right paths and
 * flags, the club branding and the house font as data URIs, and a readable
 * cause when rendering fails. Shared by the day schedule and the duty
 * overview, so a server fix works for both at once.
 */
trait RendersWithChrome
{
    /**
     * The core of a failed render, for the log line.
     *
     * Browsershot wraps Chrome's error in the command it ran; the useful text
     * only comes at the end, after "Error Output:".
     */
    protected function cause(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $stderr = Str::afterLast($message, 'Error Output:');
        $text = $stderr === $message ? $message : ltrim($stderr, "= \n\r\t");

        return Str::limit(Str::squish($text), 300);
    }

    /**
     * Browsershot with node on a path PHP can actually reach.
     *
     * The configuration wins; when it is empty NodePaths looks up the
     * install. When that finds nothing either, Browsershot may try the PATH.
     */
    protected function browser(string $html): Browsershot
    {
        // No newHeadless(): that starts the full Chrome, which needs desktop
        // libraries (cups, cairo, pango) the server lacks. The headless shell
        // renders the same without them.
        $browsershot = Browsershot::html($html)
            // Ubuntu 23.10 and later block unprivileged user namespaces via
            // AppArmor; Chrome then cannot set up its sandbox and stops with
            // "No usable sandbox". The alternative is switching that protection
            // off for the whole machine. What is rendered here is our own HTML
            // from a temporary file, without network traffic.
            ->noSandbox()
            // With a small /dev/shm Chrome hangs halfway through rendering.
            // No host resolves: everything (fonts, logo) is inline, so even a
            // slip in escaping could not make Chrome fetch an internal URL.
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'host-resolver-rules' => 'MAP * ~NOTFOUND',
            ]);

        $node = config('browsershot.node_binary') ?: NodePaths::binary('node');
        $npm = config('browsershot.npm_binary') ?: NodePaths::binary('npm');

        if (filled($node)) {
            $browsershot->setNodeBinary((string) $node);
        }
        if (filled($npm)) {
            $browsershot->setNpmBinary((string) $npm);
        }
        if (filled($chrome = config('browsershot.chrome_path'))) {
            $browsershot->setChromePath((string) $chrome);
        }

        return $browsershot;
    }

    /**
     * The club identity on the image: name, logo (data URI or null), primary
     * colour and tagline.
     *
     * @return array{club_name: string, logo: ?string, primary_color: string, tagline: ?string}
     */
    protected function branding(?PlannerSetting $settings = null): array
    {
        $settings ??= PlannerSetting::current();

        return [
            'club_name' => (string) app(CurrentClub::class)->get()?->name,
            'logo' => $this->clubLogo($settings),
            'primary_color' => PrimaryColor::from($settings->primary_color),
            'tagline' => filled($settings->schedule_tagline) ? trim((string) $settings->schedule_tagline) : null,
        ];
    }

    /**
     * The club logo from the public disk as a data URI, or null when none is
     * set or the file is missing.
     *
     * As a separate image and not inline: a logo has its own colours and
     * mask ids, which would clash inline with the second copy in the header.
     */
    protected function clubLogo(PlannerSetting $settings): ?string
    {
        $path = trim((string) $settings->logo_path);
        $disk = Storage::disk('public');
        if ($path === '' || !$disk->exists($path)) {
            return null;
        }

        $mime = Str::endsWith(Str::lower($path), '.svg') ? 'image/svg+xml' : ($disk->mimeType($path) ?: 'image/png');

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }

    /** @return array{regular: string, bold: string} */
    protected function fonts(): array
    {
        return [
            'regular' => $this->font('Outfit-Regular.ttf'),
            'bold' => $this->font('Outfit-Bold.ttf'),
        ];
    }

    protected function font(string $file): string
    {
        $path = TextMeasure::fontPath($file);

        return is_file($path) ? base64_encode((string) file_get_contents($path)) : '';
    }
}
