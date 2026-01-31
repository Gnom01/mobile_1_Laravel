<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return \App\Models\Client::orderBy('ClientsID', 'desc')->paginate(50);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $client = \App\Models\Client::findOrFail($id);

        $data = $request->only([
            'ClientName','NIP','City','ZipCode','Address','Phone','EMAIL','URL','Longitude','Latitude'
        ]);

        $client->fill($data);
        $client->WhenUpdated = now();
        $client->save();

        \App\Models\OutboxEvent::create([
            'entity' => 'clients',
            'action' => 'updated',
            'local_id' => $client->ClientsID,
            'payload' => $data,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        return $client;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
