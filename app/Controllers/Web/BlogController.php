<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BlogService;
use App\Services\ContentService;
use App\Services\SeoService;
use App\Services\SettingsService;

/** Fully server-rendered blog (best for crawlers). */
final class BlogController
{
    private PageController $pages;

    public function __construct()
    {
        $this->pages = new PageController();
    }

    private function sidebar(): array
    {
        return [
            'categories' => Database::all('SELECT c.name, c.slug, (SELECT COUNT(*) FROM blog_post_categories x JOIN blog_posts bp ON bp.id = x.post_id WHERE x.category_id = c.id AND ' . BlogService::LIVE . ') AS n
                FROM blog_categories c ORDER BY c.sort_order'),
            'popular' => BlogService::list(['sort' => 'popular', 'per_page' => 4])['items'],
            'tags' => Database::all('SELECT t.name, t.slug FROM blog_tags t WHERE EXISTS (SELECT 1 FROM blog_post_tags x JOIN blog_posts bp ON bp.id = x.post_id WHERE x.tag_id = t.id AND ' . BlogService::LIVE . ') ORDER BY t.name'),
        ];
    }

    private function pageNumber(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    public function index(Request $request): Response
    {
        $page = $this->pageNumber($request);
        $list = BlogService::list(['page' => $page, 'per_page' => 9]);
        if ($page > 1 && !$list['items']) {
            throw new HttpException(404, 'صفحه یافت نشد.');
        }
        $featured = $page === 1 ? (BlogService::list(['featured' => 1, 'per_page' => 1])['items'][0] ?? null) : null;
        $sections = [];
        if ($page === 1) {
            foreach (Database::all('SELECT name, slug FROM blog_categories ORDER BY sort_order LIMIT 4') as $cat) {
                $posts = BlogService::list(['category' => $cat['slug'], 'per_page' => 3])['items'];
                if ($posts) {
                    $sections[] = ['name' => $cat['name'], 'slug' => $cat['slug'], 'posts' => $posts];
                }
            }
        }
        $title = (string) SettingsService::get('blog_title', 'وبلاگ');
        $path = 'blog' . ($page > 1 ? '?page=' . $page : '');
        $seo = SeoService::meta(['title' => $title . ($page > 1 ? ' — صفحه ' . fa_digits($page) : ''), 'description' => SettingsService::get('blog_intro'), 'path' => $path,
            'jsonld' => [SeoService::breadcrumbs([['name' => 'خانه', 'url' => ''], ['name' => $title, 'url' => 'blog']]), SeoService::collection($title, 'blog', $list['items'])]],
            null, null, $page === 1 ? 'blog' : null);
        return $this->pages->render('blog', 'blog/index', ['title' => $title, 'intro' => SettingsService::get('blog_intro'), 'featured' => $featured, 'list' => $list,
            'sections' => $sections, 'sidebar' => $this->sidebar(), 'banner' => ContentService::banners('blog', 1)[0] ?? null, 'basePath' => 'blog'], $seo, ['pages/blog.js']);
    }

    public function category(Request $request): Response
    {
        $cat = Database::one('SELECT * FROM blog_categories WHERE slug = :s', ['s' => (string) $request->param('slug')]);
        if (!$cat) {
            throw new HttpException(404, 'دسته‌بندی یافت نشد.');
        }
        return $this->listing($request, $cat['name'], $cat['description'], 'blog/category/' . $cat['slug'], ['category' => $cat['slug']], 'blog_category', (int) $cat['id'], $cat['image_path']);
    }

    public function tag(Request $request): Response
    {
        $tag = Database::one('SELECT * FROM blog_tags WHERE slug = :s', ['s' => (string) $request->param('slug')]);
        if (!$tag) {
            throw new HttpException(404, 'برچسب یافت نشد.');
        }
        return $this->listing($request, 'برچسب: ' . $tag['name'], 'مقالات مرتبط با «' . $tag['name'] . '»', 'blog/tag/' . $tag['slug'], ['tag' => $tag['slug']], 'blog_tag', (int) $tag['id'], null);
    }

    public function author(Request $request): Response
    {
        $author = Database::one('SELECT * FROM blog_authors WHERE slug = :s', ['s' => (string) $request->param('slug')]);
        if (!$author) {
            throw new HttpException(404, 'نویسنده یافت نشد.');
        }
        return $this->listing($request, $author['name'], $author['bio'], 'blog/author/' . $author['slug'], ['author' => $author['slug']], 'author', (int) $author['id'], $author['avatar_path'], $author);
    }

    public function search(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $page = $this->pageNumber($request);
        $list = $q !== '' ? BlogService::list(['q' => $q, 'page' => $page]) : ['items' => [], 'pagination' => ['page' => 1, 'pages' => 0, 'total' => 0, 'per_page' => 9]];
        $seo = SeoService::meta(['title' => 'جستجو در مجله: ' . $q, 'path' => 'blog/search', 'robots' => 'noindex, follow']);
        return $this->pages->render('blog', 'blog/list', ['title' => $q !== '' ? 'نتایج جستجو برای «' . $q . '»' : 'جستجو در مجله', 'description' => null, 'list' => $list,
            'sidebar' => $this->sidebar(), 'basePath' => 'blog/search?q=' . rawurlencode($q), 'q' => $q, 'author' => null], $seo, ['pages/blog.js']);
    }

    private function listing(Request $request, string $title, ?string $description, string $path, array $filter, string $seoType, int $seoId, ?string $image, ?array $author = null): Response
    {
        $page = $this->pageNumber($request);
        $list = BlogService::list($filter + ['page' => $page]);
        if ($page > 1 && !$list['items']) {
            throw new HttpException(404, 'صفحه یافت نشد.');
        }
        $jsonld = [SeoService::breadcrumbs([['name' => 'خانه', 'url' => ''], ['name' => SettingsService::get('blog_title', 'وبلاگ'), 'url' => 'blog'], ['name' => $title, 'url' => $path]]),
            SeoService::collection($title, $path, $list['items'])];
        if ($author) {
            $jsonld[] = SeoService::person($author);
        }
        $seo = SeoService::meta(['title' => $title . ($page > 1 ? ' — صفحه ' . fa_digits($page) : ''), 'description' => $description, 'path' => $path . ($page > 1 ? '?page=' . $page : ''),
            'image' => $image, 'type' => $author ? 'profile' : 'website', 'jsonld' => $jsonld], $page === 1 ? $seoType : null, $page === 1 ? $seoId : null);
        return $this->pages->render('blog', 'blog/list', ['title' => $title, 'description' => $description, 'list' => $list, 'sidebar' => $this->sidebar(),
            'basePath' => $path, 'author' => $author, 'image' => $image], $seo, ['pages/blog.js']);
    }

    public function article(Request $request): Response
    {
        $post = BlogService::findLive((string) $request->param('slug'));
        if (!$post) {
            throw new HttpException(404, 'مقاله یافت نشد.');
        }
        return $this->renderArticle($post, false);
    }

    /** Admin-only preview of drafts/scheduled posts (never indexed). */
    public function preview(Request $request): Response
    {
        if (!AdminAuth::can('blog.posts')) {
            throw new HttpException(404, 'صفحه یافت نشد.');
        }
        $post = Database::one('SELECT bp.*, a.name AS author_name, a.slug AS author_slug, a.bio AS author_bio, a.avatar_path AS author_avatar, a.expertise AS author_expertise
            FROM blog_posts bp LEFT JOIN blog_authors a ON a.id = bp.author_id WHERE bp.id = :id', ['id' => (int) $request->param('id')]);
        if (!$post) {
            throw new HttpException(404, 'مقاله یافت نشد.');
        }
        $post['published_at'] ??= now_utc();
        return $this->renderArticle($post, true);
    }

    private function renderArticle(array $post, bool $preview): Response
    {
        $article = BlogService::article($post);
        $path = 'blog/' . $post['slug'];
        if (!$preview) {
            $seen = (array) Session::get('seen_posts', []);
            if (!in_array((int) $post['id'], $seen, true)) {
                Database::query('UPDATE blog_posts SET view_count = view_count + 1 WHERE id = :id', ['id' => $post['id']]);
                Session::set('seen_posts', array_slice(array_merge($seen, [(int) $post['id']]), -50));
            }
        }
        $crumbs = [['name' => 'خانه', 'url' => ''], ['name' => SettingsService::get('blog_title', 'وبلاگ'), 'url' => 'blog']];
        if ($article['categories']) {
            $crumbs[] = ['name' => $article['categories'][0]['name'], 'url' => 'blog/category/' . $article['categories'][0]['slug']];
        }
        $crumbs[] = ['name' => $post['title'], 'url' => $path];
        $seo = SeoService::meta(['title' => $post['title'], 'description' => $post['excerpt'] ?: $post['content'], 'path' => $path, 'image' => $post['featured_image'], 'type' => 'article',
            'jsonld' => [SeoService::article($post, $path), SeoService::breadcrumbs($crumbs)]] + ($preview ? ['private' => true] : []), 'post', (int) $post['id']);
        return $this->pages->render('article', 'blog/article', ['post' => $post, 'a' => $article, 'path' => $path, 'crumbs' => $crumbs, 'preview' => $preview,
            'commentsEnabled' => SettingsService::get('blog_comments_enabled') && (int) $post['allow_comments']], $seo, ['pages/blog.js']);
    }
}
