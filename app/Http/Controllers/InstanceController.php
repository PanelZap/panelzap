<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Instance;
use App\Models\Client;
use App\Http\Requests\InstanceRequest;
use App\Services\Internal\Whatsapp\WhatsappManagerService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class InstanceController extends Controller
{
    public function __construct(protected WhatsappManagerService $whatsappService){}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Instance::class);

        $instances = Instance::query()
            ->with('client.user', 'providerable')
            ->when(auth()->user()->client, function ($query, $client) {
                $query->where('client_id', $client->id);
            })
            ->paginate(10);
        
        return view('instance.index', [
            'instances' => $instances
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('create', Instance::class);

        $clients = Client::query()
            ->select('clients.*', 'plans.quantity_instance')    
            ->leftJoin('instances', 'clients.id', '=', 'instances.client_id')
            ->join('plans', 'clients.plan_id', '=', 'plans.id')
            ->where('clients.status', 'Ativo')
            ->selectRaw('count(instances.id) as instances_count')
            ->groupBy('clients.id', 'plans.id')
            ->havingRaw('plans.quantity_instance > count(instances.id)')
        ->get();

        return view('instance.create', [
            'clients' => $clients,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InstanceRequest $request)
    {
        $validatedData = $request->validated();
        
        try {
            Instance::create($validatedData);
            return redirect()->route('instances.index')->with('success', 'Instância criada com sucesso!');
        } catch (\Exception $e) {
            Log::error('Erro ao criar instância: ' . $e->getMessage());
            return back()->with('error', 'Erro ao criar instância!');
        }
    }

    public function recreate(Instance $instance)
    {
        Gate::authorize('update', $instance);

        try{
            $data = $this->whatsappService->deleteInstance($instance->name);

            if($data['status'] == 'SUCCESS') {
                $instance->update([
                    'status' => 'Aguardando ler QrCode',
                ]);
                
                retry(3, function() use ($instance) {
                    $this->whatsappService->createInstance([
                        "instanceName" => $instance->name,
                        "token" => $instance->token,
                        "integration" => "WHATSAPP-BAILEYS"
                    ]);
            
                    $this->whatsappService->setWebsocketInstance($instance->name, [
                        "enabled" => true,
                        "events" => [
                            "QRCODE_UPDATED",
                            "CONNECTION_UPDATE"
                        ]
                    ]);
                }, 2000);

                return back()->with('success', 'Instância recriada com sucesso!');
            }
    
            return back()->with('error', 'Não foi possível excluir a instância!');
        } catch (\Exception $e) {
            Log::channel('daily')->error("Erro ao recriar instância: ".json_encode($e->getMessage()));
            return back()->with('error', 'Erro ao recriar instância!');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Instance $instance)
    {
        Gate::authorize('delete', $instance);

        try {

            $this->whatsappService->deleteInstance($instance->name);

            $instance->delete();
            return back()->with('success', 'Instância excluída com sucesso');
        } catch (\Exception $exception) {
            Log::channel('daily')->error('Erro ao excluir o instância: ' . $exception->getMessage());
            return back()->with('error', 'Não foi possível excluir o instância');
        }
    }

    public function connect(Instance $instance)
    {
        return $this->whatsappService->instanceConnect($instance->name);
    }
}
