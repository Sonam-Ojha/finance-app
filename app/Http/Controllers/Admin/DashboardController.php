<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Support\Counts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin › Dashboard: every account at a glance.
 *
 * Privacy: counts and trends only. Nothing here reads what anyone recorded —
 * no query selects amount_minor, note or source, and none touches
 * categories or budgets — the admin's own data included.
 */
class DashboardController extends Controller
{
    /** ?range= → [label, length of the period]. */
    public const RANGES = [
        '7d'  => ['7 days', '7 days'],
        '30d' => ['30 days', '30 days'],
        '12m' => ['12 months', '12 months'],
    ];

    private const PLATFORMS = ['android' => 'Android', 'ios' => 'iPhone', 'web' => 'Web app'];

    public function index(Request $request)
    {
        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '30d';

        // The sums are the slow part, and five minutes old is fresh enough.
        $stats = Cache::remember("admin.dashboard.{$range}", 300, fn () => $this->aggregates($range));

        return view('admin.dashboard', $stats + [
            'range'     => $range,
            'ranges'    => self::RANGES,
            'updatedAt' => now(config('app.display_timezone'))->format('j M Y, g:i A'),
            'recent'    => $this->recentSignUps(),
            'attention' => $this->needsAttention(),
            'activity'  => AdminActivityLog::with('admin:id,name')->latest('created_at')->latest('id')->limit(5)->get(),
        ]);
    }

    /** Everything that is the same for every admin for five minutes. */
    private function aggregates(string $range): array
    {
        [$start, $previousStart] = self::period($range);
        $total = User::count();

        $joined     = User::where('created_at', '>=', $start)->count();
        $joinedPrev = User::where('created_at', '>=', $previousStart)->where('created_at', '<', $start)->count();
        $before     = $total - $joined; // accounts that existed when the period began

        $active  = $this->activeUsers($start);
        $entries = DB::table('app_expenses')->where('created_at', '>=', $start)->count()
                 + DB::table('app_incomes')->where('created_at', '>=', $start)->count();

        $versus = 'vs previous ' . self::RANGES[$range][1];

        return [
            'kpis' => [
                [
                    'label' => 'Total users', 'value' => Counts::indian($total),
                    'change' => self::change($total, $before), 'sub' => 'vs ' . self::RANGES[$range][1] . ' ago',
                ],
                [
                    'label' => 'New sign-ups', 'value' => Counts::indian($joined),
                    'change' => self::change($joined, $joinedPrev), 'sub' => $versus,
                ],
                [
                    'label' => 'Active users', 'value' => Counts::indian($active),
                    'change' => null, 'sub' => ($total ? Counts::percent($active / $total * 100) : '0.0') . '% of all users',
                ],
                [
                    'label' => 'Entries logged', 'value' => Counts::indian($entries),
                    'change' => null, 'sub' => 'Count of expenses and incomes, no amounts',
                ],
            ],
            'signups'    => $this->signUpsByMonth(),
            'platforms'  => $this->platforms($total),
            'currencies' => $this->currencies($total),
        ];
    }

    /** [start of this period, start of the one before], in the server's time. */
    private static function period(string $range): array
    {
        $now = now();
        return match ($range) {
            '7d'    => [$now->copy()->subDays(7), $now->copy()->subDays(14)],
            '12m'   => [$now->copy()->subMonths(12), $now->copy()->subMonths(24)],
            default => [$now->copy()->subDays(30), $now->copy()->subDays(60)],
        };
    }

    /** Growth from $before to $now in percent, or null when there is nothing to compare with. */
    private static function change(int $now, int $before): ?float
    {
        return $before > 0 ? ($now - $before) / $before * 100 : null;
    }

    /** Anyone signed in on the website or using the app since $start, counted once. */
    private function activeUsers(Carbon $start): int
    {
        $web = DB::table('sessions')->whereNotNull('user_id')
            ->where('last_activity', '>=', $start->getTimestamp())
            ->select('user_id as id');
        $app = DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->where('last_used_at', '>=', $start)
            ->select('tokenable_id as id');

        // UNION (not UNION ALL) drops the duplicates.
        return DB::query()->fromSub($web->union($app), 'active')->count();
    }

    /** Sign-ups in each of the last 12 months, oldest first, empty months included. */
    private function signUpsByMonth(): array
    {
        $first = now()->startOfMonth()->subMonths(11);
        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $counts = DB::table('users')
            ->where('created_at', '>=', $first)
            ->selectRaw("{$month} as ym, COUNT(*) as c")
            ->groupBy('ym')
            ->pluck('c', 'ym');

        $bars = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $first->copy()->addMonths($i);
            $bars[] = ['label' => $m->format('M'), 'long' => $m->format('M Y'), 'count' => (int) ($counts[$m->format('Y-m')] ?? 0)];
        }

        // Four even steps up to a round top: 1, 2, 2.5 or 5 times a power of ten.
        $max  = max(array_column($bars, 'count'));
        $raw  = max($max, 1) / 4;
        $mag  = 10 ** floor(log10($raw));
        $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s >= $raw);
        $step = max(1, $step);

        return ['bars' => $bars, 'top' => (int) ceil($step * 4), 'step' => $step];
    }

    /**
     * Each user once, under the platform of the phone they signed in on last;
     * no phone at all is the web app.
     */
    private function platforms(int $total): array
    {
        $latest = DB::table('device_tokens')->selectRaw('user_id, MAX(id) as id')->groupBy('user_id');
        $byPlatform = DB::table('device_tokens as d')
            ->joinSub($latest, 'l', 'l.id', '=', 'd.id')
            ->selectRaw('d.platform, COUNT(*) as c')
            ->groupBy('d.platform')
            ->pluck('c', 'platform');

        $android = (int) ($byPlatform['android'] ?? 0);
        $ios     = (int) ($byPlatform['ios'] ?? 0);
        $counts  = ['android' => $android, 'ios' => $ios, 'web' => max(0, $total - $android - $ios)];

        return collect($counts)->map(fn ($c, $key) => [
            'label' => self::PLATFORMS[$key],
            'share' => $total ? round($c / $total * 100) : 0,
        ])->values()->all();
    }

    /** The currencies people read amounts in: the top four, then everything else. */
    private function currencies(int $total): array
    {
        if ($total === 0) return [];

        $counts = DB::table('users')
            ->selectRaw("COALESCE(currency, 'INR') as code, COUNT(*) as c")
            ->groupBy('code')
            ->orderByDesc('c')
            ->pluck('c', 'code');

        $chips = $counts->take(4)->map(fn ($c, $code) => ['code' => $code, 'share' => round($c / $total * 100)])->values();
        $other = $counts->slice(4)->sum();
        if ($other > 0) $chips->push(['code' => 'Other', 'share' => round($other / $total * 100)]);

        return $chips->all();
    }

    /** The five newest accounts — who, from where, and whether they have confirmed their email. */
    private function recentSignUps(): array
    {
        $users = User::latest('created_at')->latest('id')->limit(5)->get(['id', 'name', 'email', 'avatar', 'email_verified_at', 'created_at']);
        $platforms = DB::table('device_tokens as d')
            ->joinSub(DB::table('device_tokens')->selectRaw('user_id, MAX(id) as id')->whereIn('user_id', $users->pluck('id'))->groupBy('user_id'), 'l', 'l.id', '=', 'd.id')
            ->pluck('d.platform', 'd.user_id');

        return $users->map(fn (User $u) => [
            'id'       => $u->id,
            'name'     => $u->name ?: 'No name yet',
            'email'    => $u->email,
            'masked'   => mask_email($u->email),
            'platform' => self::PLATFORMS[$platforms[$u->id] ?? 'web'] ?? 'Web app',
            'joined'   => self::when($u->created_at, true),
            'verified' => $u->email_verified_at !== null,
            'user'     => $u, // for the photo (components/admin/avatar)
        ])->all();
    }

    /** Only what actually needs an admin now. */
    private function needsAttention(): array
    {
        $items = [];

        $unverified = User::whereNull('email_verified_at')->count();
        if ($unverified > 0) {
            $items[] = [
                'level' => 'medium',
                'text'  => Counts::indian($unverified) . ' ' . ($unverified === 1 ? "user hasn't" : "users haven't") . ' verified their email',
                'link'  => route('admin.users.index', ['status' => 'unverified']),
                'cta'   => 'See who',
            ];
        }

        $failed = (int) DB::table('admin_notifications')->where('created_at', '>=', now()->subDays(7))->sum('failed');
        if ($failed > 0) {
            $items[] = [
                'level' => 'low',
                'text'  => Counts::indian($failed) . ' push ' . ($failed === 1 ? 'notification' : 'notifications') . ' failed last week',
                'link'  => route('admin.notifications.index'),
                'cta'   => 'Open notifications',
            ];
        }

        // Help & support requests nobody has answered yet; urgent once one has waited a day.
        $supportCount = \App\Models\SupportTicket::waitingCount();
        if ($supportCount > 0) {
            $oldest = \App\Models\SupportTicket::where('status', \App\Models\SupportTicket::OPEN)->min('updated_at');
            array_unshift($items, [
                'level' => $oldest && Carbon::parse($oldest)->lt(now()->subDay()) ? 'high' : 'medium',
                'text'  => Counts::indian($supportCount) . ' support ' . ($supportCount === 1 ? 'request' : 'requests') . ' waiting',
                'link'  => route('admin.support.index', ['status' => \App\Models\SupportTicket::OPEN]),
                'cta'   => 'Reply',
            ]);
        }

        if (\App\Support\AppControl::maintenance()) {
            array_unshift($items, [
                'level' => 'high',
                'text'  => 'Maintenance mode is on: only admins can use the app and the website',
                'link'  => route('admin.app-control'),
                'cta'   => 'Open app control',
            ]);
        }

        // TODO: spam sign-ups from one IP address.
        // TODO: account deletion requests.
        // TODO: users below the minimum app version.

        return $items;
    }

    /** "Today, 9:42 AM", "Yesterday, 4:30 PM", "26 Oct, 7:20 PM" — or, short, "10:12 AM", "Yesterday", "24 Oct". */
    public static function when(?Carbon $at, bool $withTime = false): string
    {
        if (! $at) return '—';
        $local = $at->copy()->timezone(config('app.display_timezone'));
        $today = now(config('app.display_timezone'))->toDateString();
        $time  = $local->format('g:i A');

        if ($local->toDateString() === $today) return $withTime ? "Today, {$time}" : $time;
        if ($local->toDateString() === Carbon::parse($today)->subDay()->toDateString()) return $withTime ? "Yesterday, {$time}" : 'Yesterday';

        return $local->format('j M') . ($withTime ? ", {$time}" : '');
    }
}
