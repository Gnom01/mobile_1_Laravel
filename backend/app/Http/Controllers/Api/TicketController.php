<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * GET /api/offers/tickets
     */
    public function index(Request $request)
    {
        $query = Ticket::query()->where('website_status_id', '!=', 0);
        $this->applyFilters($query, $request);

        $items = $query->orderBy('starts_at')->get();

        return response()->json([
            'status'      => '200',
            'body'        => $items->map(fn ($t) => $this->mapTicket($t)),
            'recordCount' => $items->count(),
        ]);
    }

    /**
     * GET /api/offers/tickets/{id}
     */
    public function show(int $id)
    {
        $ticket = Ticket::where('crm_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        return response()->json([
            'status' => '200',
            'body'   => $this->mapTicket($ticket),
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('localizationID')) {
            $query->where('localization_id', (int) $request->input('localizationID'));
        }
        if ($request->filled('ageRangeID')) {
            $query->where('age_range_id', (int) $request->input('ageRangeID'));
        }
        if ($request->filled('categoryID')) {
            $query->where('category_id', (int) $request->input('categoryID'));
        }
        if ($request->filled('levelID')) {
            $query->where('level_id', (int) $request->input('levelID'));
        }
        if ($request->filled('styleID')) {
            $query->where('style_id', (int) $request->input('styleID'));
        }
        if ($request->filled('dateFrom')) {
            $query->where('starts_at', '>=', $request->input('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->where('ends_at', '<=', $request->input('dateTo'));
        }
        if ($request->filled('eventID')) {
            $query->where('event_id', (int) $request->input('eventID'));
        }
        if ($request->filled('ticketType')) {
            $query->where('ticket_type', $request->input('ticketType'));
        }
        if ($request->boolean('availableOnly')) {
            $query->where('is_closed', 0)->where('available_places', '>', 0);
        }
    }

    private function mapTicket(Ticket $ticket): array
    {
        return [
            'id'               => $ticket->crm_id,
            'title'            => $ticket->title,
            'description'      => $ticket->description,
            'offerType'        => $ticket->offer_type,
            'websiteStatusId'  => $ticket->website_status_id,
            'isClosed'         => (bool) $ticket->is_closed,
            'startsAt'         => $ticket->starts_at?->toDateString(),
            'endsAt'           => $ticket->ends_at?->toDateString(),
            'localizationId'   => $ticket->localization_id,
            'localizationName' => $ticket->localization_name,
            'ageRangeId'       => $ticket->age_range_id,
            'ageRangeName'     => $ticket->age_range_name,
            'categoryId'       => $ticket->category_id,
            'categoryName'     => $ticket->category_name,
            'levelId'          => $ticket->level_id,
            'levelName'        => $ticket->level_name,
            'styleId'          => $ticket->style_id,
            'styleName'        => $ticket->style_name,
            'instructors'      => $ticket->instructors,
            'nextEventDate'    => $ticket->next_event_date?->toDateString(),
            'startTime'        => $ticket->start_time,
            'availablePlaces'  => $ticket->available_places,
            'capacity'         => $ticket->capacity,
            'eventId'          => $ticket->event_id,
            'ticketType'       => $ticket->ticket_type,
            'priceFrom'        => (float) $ticket->price_from,
            'saleStartsAt'     => $ticket->sale_starts_at?->toDateTimeString(),
            'saleEndsAt'       => $ticket->sale_ends_at?->toDateTimeString(),
            'coursesHeadingsId' => $ticket->courses_headings_id,
            'productsId'       => $ticket->products_id,
        ];
    }
}
