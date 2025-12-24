<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\Hotel;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('payment_methods.view');

        $paymentMethods = PaymentMethod::query()
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'tenant_id' => $method->tenant_id,
                'hotel_id' => $method->hotel_id,
                'name' => $method->name,
                'code' => $method->code,
                'type' => $method->type,
                'is_active' => $method->is_active,
                'is_default' => $method->is_default,
                'provider' => $method->provider,
                'account_number' => $method->account_number,
                'config' => $method->config,
            ]);

        return Inertia::render('Config/PaymentMethods/PaymentMethodIndex', [
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('id', $this->activeHotelId($request)))
            ->firstOrFail();

        $config = $this->decodeConfig($data['config'] ?? null);

        PaymentMethod::query()->create([
            ...$data,
            'config' => $config,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.payment-methods.index')->with('success', 'Méthode créée.');
    }

    public function update(UpdatePaymentMethodRequest $request, int $id): RedirectResponse
    {
        $data = $request->validated();

        $paymentMethod = PaymentMethod::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $config = $this->decodeConfig($data['config'] ?? null);

        $paymentMethod->update([
            ...$data,
            'config' => $config,
        ]);

        return redirect()->route('ressources.payment-methods.index')->with('success', 'Méthode mise à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorize('payment_methods.delete');

        $paymentMethod = PaymentMethod::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $paymentMethod->delete();

        return redirect()->route('ressources.payment-methods.index')->with('success', 'Méthode supprimée.');
    }

    private function decodeConfig(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
