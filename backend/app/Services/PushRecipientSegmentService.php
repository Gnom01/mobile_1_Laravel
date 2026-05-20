<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PushRecipientSegmentService
{
    public function query(array $filters = []): Builder
    {
        $query = DB::table('users as u')
            ->select([
                'u.UsersID as user_id',
                'u.guid',
                'u.FirstName as first_name',
                'u.LastName as last_name',
                'u.Email as email',
                'u.Phone as phone',
                'u.City as city',
                'u.DateOfBirdth as birth_date',
                'u.Default_LocalizationsID as default_localization_id',
                'u.marketingAgreement as marketing_agreement',
            ])
            ->where('u.Cancelled', 0);

        $this->applyListFilter($query, 'u.UsersID', $filters['user_ids'] ?? null);
        $this->applyListFilter($query, 'u.guid', $filters['user_guids'] ?? null);
        $this->applyListFilter($query, 'u.Default_LocalizationsID', $filters['school_ids'] ?? ($filters['localization_ids'] ?? null));
        $this->applyListFilter($query, 'u.City', $filters['cities'] ?? null);

        if (($filters['active'] ?? null) !== null) {
            $query->where('u.Active', (int) (bool) $filters['active']);
        }

        if (($filters['marketing_consent'] ?? null) !== null) {
            $query->where('u.marketingAgreement', (int) (bool) $filters['marketing_consent']);
        }

        if (!empty($filters['birth_date_from'])) {
            $query->whereDate('u.DateOfBirdth', '>=', $filters['birth_date_from']);
        }

        if (!empty($filters['birth_date_to'])) {
            $query->whereDate('u.DateOfBirdth', '<=', $filters['birth_date_to']);
        }

        if (isset($filters['age_from']) || isset($filters['age_to'])) {
            $today = now();
            if (isset($filters['age_from'])) {
                $query->whereDate('u.DateOfBirdth', '<=', $today->copy()->subYears((int) $filters['age_from'])->toDateString());
            }
            if (isset($filters['age_to'])) {
                $query->whereDate('u.DateOfBirdth', '>=', $today->copy()->subYears(((int) $filters['age_to']) + 1)->addDay()->toDateString());
            }
        }

        if (!empty($filters['has_mobile_app']) || !empty($filters['has_active_push_token'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('device_tokens as dt')
                    ->whereColumn('dt.user_id', 'u.UsersID');

                if (!empty($filters['has_active_push_token'])) {
                    $sub->where('dt.is_active', true);
                }
            });
        }

        if (!empty($filters['last_seen_from']) || !empty($filters['last_seen_to'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('device_tokens as dt')
                    ->whereColumn('dt.user_id', 'u.UsersID');

                if (!empty($filters['last_seen_from'])) {
                    $sub->where('dt.last_seen_at', '>=', $filters['last_seen_from']);
                }
                if (!empty($filters['last_seen_to'])) {
                    $sub->where('dt.last_seen_at', '<=', $filters['last_seen_to']);
                }
            });
        }

        $this->applyUsersProductFilters($query, $filters);
        $this->applyScheduleFilters($query, $filters);
        $this->applyWorkshopFilters($query, $filters);
        $this->applyOfferFilters($query, $filters);
        $this->applyPaymentFilters($query, $filters);

        if (!empty($filters['exclude_user_ids'])) {
            $query->whereNotIn('u.UsersID', (array) $filters['exclude_user_ids']);
        }

        return $query->distinct();
    }

    public function preview(array $filters = [], int $limit = 100): array
    {
        $baseQuery = $this->query($filters);
        $count = (clone $baseQuery)->count('u.UsersID');
        $recipients = (clone $baseQuery)
            ->orderBy('u.LastName')
            ->orderBy('u.FirstName')
            ->limit($limit)
            ->get();

        return ['count' => $count, 'recipients' => $recipients];
    }

    public function userIds(array $filters = []): array
    {
        return $this->query($filters)
            ->pluck('u.UsersID')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function applyUsersProductFilters(Builder $query, array $filters): void
    {
        $keys = ['course_heading_ids', 'course_ids', 'style_ids', 'status_ids', 'localization_ids'];
        if (!$this->hasAny($filters, $keys)) {
            return;
        }

        $query->whereExists(function ($sub) use ($filters) {
            $sub->selectRaw('1')
                ->from('usersproducts as up')
                ->whereColumn('up.usersid', 'u.UsersID')
                ->where('up.cancelled', 0);

            $this->applyListFilter($sub, 'up.coursesheadingsid', $filters['course_heading_ids'] ?? ($filters['group_ids'] ?? null));
            $this->applyListFilter($sub, 'up.productsid', $filters['course_ids'] ?? null);
            $this->applyListFilter($sub, 'up.productslevel3dvid', $filters['style_ids'] ?? null);
            $this->applyListFilter($sub, 'up.usersproductsstatusdvid', $filters['status_ids'] ?? null);
            $this->applyListFilter($sub, 'up.localizationsid', $filters['localization_ids'] ?? null);
        });
    }

    private function applyScheduleFilters(Builder $query, array $filters): void
    {
        if (!$this->hasAny($filters, ['schedule_event_ids', 'instructor_ids', 'group_ids'])) {
            return;
        }

        $query->whereExists(function ($sub) use ($filters) {
            $sub->selectRaw('1')
                ->from('usersschedules as us')
                ->whereColumn('us.usersid', 'u.UsersID')
                ->where('us.cancelled', 0);

            $this->applyListFilter($sub, 'us.scheduleseventssettlementsid', $filters['schedule_event_ids'] ?? null);
            $this->applyListFilter($sub, 'us.coursesheadingsid', $filters['group_ids'] ?? null);

            if (!empty($filters['instructor_ids'])) {
                $sub->join('scheduleseventssettlements as ses', 'ses.schedulesEventsSettlementsID', '=', 'us.scheduleseventssettlementsid');
                $sub->where(function ($instructorQuery) use ($filters) {
                    foreach ((array) $filters['instructor_ids'] as $instructorId) {
                        $instructorQuery->orWhereRaw('FIND_IN_SET(?, ses.instructorsIDList)', [(int) $instructorId]);
                    }
                });
            }
        });
    }

    private function applyWorkshopFilters(Builder $query, array $filters): void
    {
        if (!$this->hasAny($filters, ['workshop_ids'])) {
            return;
        }

        $query->whereExists(function ($sub) use ($filters) {
            $sub->selectRaw('1')
                ->from('userworkshopsgroups as uwg')
                ->whereColumn('uwg.usersid', 'u.UsersID')
                ->where('uwg.cancelled', 0);

            $this->applyListFilter($sub, 'uwg.workshopproductwrapperid', $filters['workshop_ids'] ?? null);
        });
    }

    private function applyOfferFilters(Builder $query, array $filters): void
    {
        foreach ([
            'camp_ids' => 'camps',
            'day_camp_ids' => 'day_camps',
        ] as $filterKey => $table) {
            if (empty($filters[$filterKey])) {
                continue;
            }

            $query->whereExists(function ($sub) use ($filters, $filterKey, $table) {
                $sub->selectRaw('1')
                    ->from('usersproducts as up')
                    ->join($table . ' as offer', 'offer.products_id', '=', 'up.productsid')
                    ->whereColumn('up.usersid', 'u.UsersID')
                    ->where('up.cancelled', 0);

                $this->applyListFilter($sub, 'offer.crm_id', $filters[$filterKey]);
            });
        }
    }

    private function applyPaymentFilters(Builder $query, array $filters): void
    {
        if (empty($filters['payment_status'])) {
            return;
        }

        $query->whereExists(function ($sub) use ($filters) {
            $sub->selectRaw('1')
                ->from('userspaymentsschedules as ups')
                ->whereColumn('ups.usersID', 'u.UsersID');

            if ($filters['payment_status'] === 'overdue') {
                $sub->where('ups.leftToPaid', '>', 0)->whereDate('ups.paymentDate', '<', now()->toDateString());
            } elseif ($filters['payment_status'] === 'unpaid') {
                $sub->where('ups.leftToPaid', '>', 0);
            } elseif ($filters['payment_status'] === 'paid') {
                $sub->where('ups.leftToPaid', '<=', 0);
            }
        });
    }

    private function applyListFilter(Builder $query, string $column, mixed $values): void
    {
        if ($values === null || $values === '') {
            return;
        }

        $values = is_array($values) ? $values : [$values];
        $values = array_values(array_filter($values, fn ($value) => $value !== null && $value !== ''));

        if ($values) {
            $query->whereIn($column, $values);
        }
    }

    private function hasAny(array $filters, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!empty($filters[$key])) {
                return true;
            }
        }

        return false;
    }
}
