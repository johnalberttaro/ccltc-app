<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\TennisEvent;
use App\Models\Tournament;

// sitemap.xml: the list of public pages, so Google can find them all
class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('tournaments.index'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('events.index'), 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => route('announcements.index'), 'changefreq' => 'weekly', 'priority' => '0.7'],
        ];

        foreach (Tournament::query()->public()->latest('updated_at')->take(200)->get() as $t) {
            $urls[] = ['loc' => route('tournaments.show', $t), 'lastmod' => $t->updated_at, 'changefreq' => 'weekly', 'priority' => '0.6'];
        }
        foreach (TennisEvent::query()->latest('updated_at')->take(200)->get() as $e) {
            $urls[] = ['loc' => route('events.show', $e), 'lastmod' => $e->updated_at, 'changefreq' => 'monthly', 'priority' => '0.5'];
        }
        foreach (Announcement::published()->latest('updated_at')->take(200)->get() as $a) {
            $urls[] = ['loc' => route('announcements.show', $a), 'lastmod' => $a->updated_at, 'changefreq' => 'monthly', 'priority' => '0.5'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . e($u['loc']) . '</loc>'
                . (isset($u['lastmod']) && $u['lastmod'] ? '<lastmod>' . $u['lastmod']->toAtomString() . '</lastmod>' : '')
                . '<changefreq>' . $u['changefreq'] . '</changefreq><priority>' . $u['priority'] . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}