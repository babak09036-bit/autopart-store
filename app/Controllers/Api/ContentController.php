<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\BlogService;
use App\Services\ContentService;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Validators\Validator;

final class ContentController extends Controller
{
    /** Boot data for the storefront: CSRF token, current user, public settings, menus. */
    public function boot(): Response
    {
        $user = Auth::user();
        return $this->ok([
            'csrf' => Csrf::token(),
            'user' => $user ? ['name' => $user['full_name']] : null,
            'settings' => SettingsService::all(true),
            'menus' => ContentService::menus(),
        ]);
    }

    public function settings(): Response
    {
        return $this->ok(SettingsService::all(true));
    }

    public function menus(): Response
    {
        return $this->ok(ContentService::menus());
    }

    public function banners(Request $request): Response
    {
        $placement = (string) $request->query('placement', 'hero');
        if (!isset(ContentService::BANNER_PLACEMENTS[$placement])) {
            throw new HttpException(422, 'محل نمایش بنر معتبر نیست.');
        }
        return $this->ok(ContentService::banners($placement));
    }

    public function homepage(): Response
    {
        return $this->ok(ContentService::homepage());
    }

    public function page(Request $request): Response
    {
        $page = ContentService::page((string) $request->param('slug'));
        if (!$page) {
            throw new HttpException(404, 'صفحه یافت نشد.');
        }
        if ($page['template'] === 'faq') {
            $page['faqs'] = ContentService::faqs();
        }
        return $this->ok($page);
    }

    public function faqs(): Response
    {
        return $this->ok(ContentService::faqs());
    }

    public function contact(Request $request): Response
    {
        RateLimiter::enforce('contact:' . $request->ip(), 5, 3600);
        $data = Validator::validate($request->all(), [
            'name' => 'required|string|minlen:3|maxlen:120',
            'contact' => 'required|string|maxlen:190',
            'subject' => 'required|string|maxlen:120',
            'message' => 'required|string|minlen:10|maxlen:3000',
        ]);
        $contact = to_en_digits($data['contact']);
        if (!preg_match('/^09\d{9}$/', $contact) && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', ['contact' => 'یک شماره موبایل (۰۹...) یا ایمیل معتبر وارد کنید.']);
        }
        if (trim((string) $request->input('website', '')) !== '') {
            return $this->ok(null, 'پیام شما ثبت شد.'); // honeypot: silently ignore bots
        }
        Database::insert('contact_messages', $data + ['ip' => $request->ip()]);
        NotificationService::admins('message.new', 'پیام جدید: ' . $data['subject'], mb_substr($data['message'], 0, 120), '#/messages');
        return $this->ok(null, 'پیام شما ثبت شد. کارشناسان ما حداکثر تا یک روز کاری پاسخ می‌دهند.', 201);
    }

    public function newsletter(Request $request): Response
    {
        RateLimiter::enforce('newsletter:' . $request->ip(), 10, 3600);
        $data = Validator::validate($request->all(), ['email' => 'required|email']);
        $existing = Database::one('SELECT id, status FROM newsletter_subscribers WHERE email = :e', ['e' => $data['email']]);
        if ($existing && $existing['status'] === 'subscribed') {
            throw new HttpException(409, 'این ایمیل قبلاً در خبرنامه عضو شده است.');
        }
        if ($existing) {
            Database::query("UPDATE newsletter_subscribers SET status = 'subscribed' WHERE id = :id", ['id' => $existing['id']]);
        } else {
            Database::insert('newsletter_subscribers', ['email' => $data['email'], 'ip' => $request->ip()]);
        }
        return $this->ok(null, 'عضویت شما با موفقیت انجام شد. از همراهی‌تان ممنونیم!', 201);
    }

    /* ---------------- Blog ---------------- */

    public function posts(Request $request): Response
    {
        return $this->ok(BlogService::list([
            'page' => (int) $request->query('page', 1), 'per_page' => (int) $request->query('per_page', 9),
            'category' => $request->query('category'), 'tag' => $request->query('tag'), 'author' => $request->query('author'),
            'featured' => $request->query('featured') === '1', 'sort' => $request->query('sort'), 'q' => $request->query('q'),
        ]));
    }

    public function post(Request $request): Response
    {
        $post = BlogService::findLive((string) $request->param('slug'));
        if (!$post) {
            throw new HttpException(404, 'مقاله یافت نشد.');
        }
        $article = BlogService::article($post);
        return $this->ok([
            'id' => (int) $post['id'], 'title' => $post['title'], 'subtitle' => $post['subtitle'], 'slug' => $post['slug'],
            'excerpt' => $post['excerpt'], 'content' => $article['html'], 'toc' => $article['toc'],
            'image' => $post['featured_image'], 'image_alt' => $post['featured_image_alt'], 'caption' => $post['featured_image_caption'],
            'published_at' => $post['published_at'], 'updated_at' => $post['updated_at'], 'reading_minutes' => (int) $post['reading_minutes'],
            'author' => $post['author_name'] ? ['name' => $post['author_name'], 'slug' => $post['author_slug'], 'bio' => $post['author_bio']] : null,
            'categories' => $article['categories'], 'tags' => $article['tags'], 'related' => $article['related'],
            'products' => $article['products'], 'comments' => $article['comments'],
        ]);
    }

    public function blogCategories(): Response
    {
        return $this->ok(Database::all('SELECT c.name, c.slug, c.description, c.image_path AS image,
            (SELECT COUNT(*) FROM blog_post_categories x JOIN blog_posts bp ON bp.id = x.post_id WHERE x.category_id = c.id AND ' . BlogService::LIVE . ') AS post_count
            FROM blog_categories c ORDER BY c.sort_order, c.name'));
    }

    public function blogTags(): Response
    {
        return $this->ok(Database::all('SELECT t.name, t.slug, (SELECT COUNT(*) FROM blog_post_tags x JOIN blog_posts bp ON bp.id = x.post_id WHERE x.tag_id = t.id AND ' . BlogService::LIVE . ') AS post_count
            FROM blog_tags t ORDER BY t.name'));
    }

    public function author(Request $request): Response
    {
        $author = Database::one('SELECT name, slug, avatar_path AS avatar, bio, expertise FROM blog_authors WHERE slug = :s', ['s' => (string) $request->param('slug')]);
        if (!$author) {
            throw new HttpException(404, 'نویسنده یافت نشد.');
        }
        $author['posts'] = BlogService::list(['author' => $author['slug'], 'page' => (int) $request->query('page', 1)]);
        return $this->ok($author);
    }

    public function blogSearch(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        if (mb_strlen($q) < 2) {
            throw new HttpException(422, 'عبارت جستجو باید حداقل ۲ کاراکتر باشد.');
        }
        return $this->ok(BlogService::list(['q' => $q, 'page' => (int) $request->query('page', 1)]));
    }

    /** Comments are moderated; honeypot + rate limit + link limit against spam. */
    public function storeComment(Request $request): Response
    {
        if (!SettingsService::get('blog_comments_enabled')) {
            throw new HttpException(403, 'ثبت دیدگاه غیرفعال است.');
        }
        $post = BlogService::findLive((string) $request->param('slug'));
        if (!$post || !(int) $post['allow_comments']) {
            throw new HttpException(404, 'امکان ثبت دیدگاه برای این مقاله وجود ندارد.');
        }
        RateLimiter::enforce('comment:' . $request->ip(), 5, 3600);
        $user = Auth::user();
        $data = Validator::validate($request->all(), [
            'author_name' => ($user ? 'nullable' : 'required') . '|string|minlen:2|maxlen:120',
            'author_email' => 'nullable|email',
            'body' => 'required|string|minlen:5|maxlen:2000',
            'parent_id' => 'nullable|int',
        ]);
        if (trim((string) $request->input('website', '')) !== '') {
            return $this->ok(null, 'دیدگاه شما ثبت شد.');
        }
        $links = preg_match_all('#https?://#i', $data['body']);
        $parent = !empty($data['parent_id']) ? Database::one("SELECT id FROM blog_comments WHERE id = :id AND post_id = :p AND status = 'approved' AND parent_id IS NULL",
            ['id' => $data['parent_id'], 'p' => $post['id']]) : null;
        Database::insert('blog_comments', [
            'post_id' => $post['id'],
            'parent_id' => $parent['id'] ?? null,
            'user_id' => $user['id'] ?? null,
            'author_name' => $user ? $user['full_name'] : $data['author_name'],
            'author_email' => $data['author_email'] ?? null,
            'body' => $data['body'],
            'status' => $links > 2 ? 'spam' : 'pending',
            'ip' => $request->ip(),
        ]);
        NotificationService::admins('comment.new', 'دیدگاه جدید برای «' . mb_substr($post['title'], 0, 80) . '»', null, '#/blog-comments');
        return $this->ok(null, 'دیدگاه شما ثبت شد و پس از بررسی منتشر می‌شود.', 201);
    }

    public function reportComment(Request $request): Response
    {
        RateLimiter::enforce('comment-report:' . $request->ip(), 10, 3600);
        $id = $this->intParam($request, 'id');
        Database::query("UPDATE blog_comments SET report_count = report_count + 1 WHERE id = :id AND status = 'approved'", ['id' => $id]);
        return $this->ok(null, 'گزارش شما ثبت شد.');
    }
}
