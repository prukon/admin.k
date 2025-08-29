<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractsController extends Controller
{
    public function index(Request $request)
    {
        $q = Contract::query()
            ->when($request->status, fn($qq) => $qq->where('status', $request->status))
            ->when($request->group_id, fn($qq) => $qq->where('group_id', $request->group_id))
            ->orderByDesc('id');

        $contracts = $q->paginate(20);

        return view('contracts.index', compact('contracts'));
    }

    public function create()
    {
        return view('contracts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_id' => ['required','integer'],
            'user_id'   => ['required','integer'], // ученик
            'group_id'  => ['nullable','integer'],
            'pdf'       => ['required','file','mimes:pdf','max:10240'], // до ~10 МБ
        ], [], [
            'pdf' => 'PDF-файл договора',
        ]);

        $path = $request->file('pdf')->store('documents/'.date('Y/m'));

        $fullPath = Storage::path($path);
        $sha = hash_file('sha256', $fullPath);

        $contract = Contract::create([
            'school_id'       => $validated['school_id'],
            'user_id'         => $validated['user_id'],
            'group_id'        => $validated['group_id'] ?? null,
            'source_pdf_path' => $path,
            'source_sha256'   => $sha,
            'status'          => Contract::STATUS_DRAFT,
            'provider'        => 'podpislon',
        ]);

        ContractEvent::create([
            'contract_id' => $contract->id,
            'type'        => 'created',
            'payload_json'=> null,
        ]);

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', 'Договор создан. Теперь можно отправить на подпись.');
    }

    public function show(Contract $contract)
    {
        $events = $contract->events()->orderBy('id','desc')->get();
        $requests = $contract->signRequests()->orderBy('id','desc')->get();

        return view('contracts.show', compact('contract','events','requests'));
    }

    public function downloadOriginal(Contract $contract)
    {
        return Storage::download($contract->source_pdf_path, 'contract-'.$contract->id.'.pdf');
    }

    public function downloadSigned(Contract $contract)
    {
        abort_unless($contract->signed_pdf_path, 404);
        return Storage::download($contract->signed_pdf_path, 'contract-'.$contract->id.'-signed.pdf');
    }

    public function send(Contract $contract, Request $request, SignatureProvider $provider)
    {
        $validated = $request->validate([
            'signer_name'  => ['nullable','string','max:255'],
            'signer_phone' => ['required','string','max:32'],
            'ttl_hours'    => ['nullable','integer','min:1','max:168'],
        ]);

        // создаём запись отправки
        $sr = new ContractSignRequest([
            'signer_name' => $validated['signer_name'] ?? null,
            'signer_phone'=> $validated['signer_phone'],
            'ttl_hours'   => $validated['ttl_hours'] ?? 72,
            'status'      => 'created',
        ]);
        $contract->signRequests()->save($sr);

        try {
            $res = $provider->send($contract, $sr);

            $contract->status = Contract::STATUS_SENT;
            $contract->save();

            ContractEvent::create([
                'contract_id' => $contract->id,
                'type'        => 'sent',
                'payload_json'=> json_encode($res, JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['message' => 'Отправлено на подпись','status'=>'sent']);
        } catch (\Throwable $e) {
            $sr->status = 'failed';
            $sr->save();

            $contract->status = Contract::STATUS_FAILED;
            $contract->save();

            ContractEvent::create([
                'contract_id' => $contract->id,
                'type'        => 'failed',
                'payload_json'=> json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function resend(Contract $contract, Request $request, SignatureProvider $provider)
    {
        // Повторную отправку делаем как новую запись sign_request
        return $this->send($contract, $request, $provider);
    }

    public function revoke(Contract $contract, SignatureProvider $provider)
    {
        try {
            $provider->revoke($contract);

            $contract->status = Contract::STATUS_REVOKED;
            $contract->save();

            ContractEvent::create([
                'contract_id' => $contract->id,
                'type'        => 'revoked',
                'payload_json'=> null,
            ]);

            return response()->json(['message'=>'Подписание отозвано','status'=>'revoked']);
        } catch (\Throwable $e) {
            ContractEvent::create([
                'contract_id' => $contract->id,
                'type'        => 'failed',
                'payload_json'=> json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function status(Contract $contract, SignatureProvider $provider)
    {
        try {
            $data = $provider->getStatus($contract);
            $status = $this->mapProviderStatus($data['status'] ?? null);

            if ($status && $status !== $contract->status) {
                $contract->status = $status;
                $contract->save();

                ContractEvent::create([
                    'contract_id' => $contract->id,
                    'type'        => 'status_sync',
                    'payload_json'=> json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);

                if ($status === Contract::STATUS_SIGNED && !$contract->signed_pdf_path) {
                    $this->downloadAndAttachSigned($contract, $provider);
                }
            }

            return response()->json(['status' => $contract->status, 'raw'=>$data]);
        } catch (\Throwable $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    protected function mapProviderStatus(?string $s): ?string
    {
        return match($s) {
        'sent'    => Contract::STATUS_SENT,
            'opened'  => Contract::STATUS_OPENED,
            'signed'  => Contract::STATUS_SIGNED,
            'expired' => Contract::STATUS_EXPIRED,
            'revoked' => Contract::STATUS_REVOKED,
            'failed'  => Contract::STATUS_FAILED,
            default   => null,
        };
    }

    protected function downloadAndAttachSigned(Contract $contract, SignatureProvider $provider): void
    {
        $file = $provider->downloadSigned($contract);
        $path = 'documents/'.date('Y/m').'/'.$file['filename'];
        Storage::put($path, $file['content']);
        $contract->signed_pdf_path = $path;
        $contract->signed_at = now();
        $contract->save();

        ContractEvent::create([
            'contract_id' => $contract->id,
            'type'        => 'signed_pdf_saved',
            'payload_json'=> json_encode(['path'=>$path], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
