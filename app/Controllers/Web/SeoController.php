<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingsService;
use App\Services\SitemapService;

final class SeoController
{
    public function sitemapIndex(): Response
    {
        return Response::text(SitemapService::index(), 'application/xml');
    }

    public function sitemap(Request $request): Response
    {
        $xml = SitemapService::section((string) $request->param('section'));
        if ($xml === null) {
            throw new HttpException(404, 'سایت‌مپ یافت نشد.');
        }
        return Response::text($xml, 'application/xml');
    }

    public function robots(): Response
    {
        return Response::text($this->robotsText());
    }

    /** Private areas are also protected by authentication — robots.txt is only a crawl hint. */
    public function robotsText(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: ' . url('admin'),
            'Disallow: ' . url('api/'),
            'Disallow: ' . url('cart'),
            'Disallow: ' . url('checkout'),
            'Disallow: ' . url('account'),
            'Disallow: ' . url('login'),
            'Disallow: ' . url('search'),
            'Disallow: ' . url('orders/'),
            'Disallow: ' . url('blog/preview/'),
        ];
        $extra = trim((string) SettingsService::get('robots_extra', ''));
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . absolute_url('sitemap.xml');
        return implode("\n", $lines) . "\n";
    }
}
