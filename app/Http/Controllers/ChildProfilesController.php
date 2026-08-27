<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\ClinicalTestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perfis infantis sob a conta do responsável.
 *
 * Toda consulta filtra por guardian_user_id: um responsável nunca alcança o
 * perfil de filho de outra pessoa, e os dados de um irmão não se misturam com
 * os do outro (escopo, Parte 01).
 */
class ChildProfilesController extends Controller
{
    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    public function index(Request $request, string $slug): Response
    {
        $user = $request->user();
        $product = $this->getProduct($request);

        $perfis = ChildProfile::doResponsavel((int) $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (ChildProfile $p) => $this->formatar($p));

        return Inertia::render('MemberAreaApp/PerfisInfantis', [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'config' => $product->member_area_config,
            'perfis' => $perfis,
            'vinculos' => ChildProfile::VINCULOS,
            'slug' => $slug,
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $validado = $this->validar($request);
        $user = $request->user();

        $perfil = ChildProfile::create([
            'guardian_user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            ...$validado,
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Perfil de {$perfil->name} criado.",
            'perfil' => $this->formatar($perfil),
        ], 201);
    }

    public function update(Request $request, string $slug, int $perfilId): JsonResponse
    {
        $perfil = $this->doResponsavelOuFalha($request, $perfilId);
        $perfil->update($this->validar($request));

        return response()->json([
            'ok' => true,
            'message' => 'Perfil atualizado.',
            'perfil' => $this->formatar($perfil->fresh()),
        ]);
    }

    /**
     * Excluir o perfil leva junto os rastreios daquela criança — é o que a
     * família espera de "apagar o perfil do meu filho".
     */
    public function destroy(Request $request, string $slug, int $perfilId): JsonResponse
    {
        $perfil = $this->doResponsavelOuFalha($request, $perfilId);
        $nome = $perfil->name;

        DB::transaction(function () use ($perfil) {
            ClinicalTestSession::where('child_profile_id', $perfil->id)->delete();
            $perfil->delete();
        });

        return response()->json([
            'ok' => true,
            'message' => "Perfil de {$nome} e os rastreios dele foram excluídos.",
        ]);
    }

    private function doResponsavelOuFalha(Request $request, int $perfilId): ChildProfile
    {
        $perfil = ChildProfile::doResponsavel((int) $request->user()->id)
            ->where('id', $perfilId)
            ->first();

        abort_if($perfil === null, 404, 'Perfil não encontrado.');

        return $perfil;
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'relationship' => ['required', 'string', 'in:'.implode(',', ChildProfile::VINCULOS)],
            'relationship_other' => ['nullable', 'string', 'max:60', 'required_if:relationship,outro'],
        ], [
            'relationship_other.required_if' => 'Diga qual é o seu vínculo com a criança.',
            'birth_date.before' => 'A data de nascimento precisa ser no passado.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatar(ChildProfile $perfil): array
    {
        return [
            'id' => $perfil->id,
            'name' => $perfil->name,
            'birth_date' => $perfil->birth_date?->format('Y-m-d'),
            'idade' => $perfil->idade(),
            'relationship' => $perfil->relationship,
            'relationship_other' => $perfil->relationship_other,
            'vinculo_legivel' => $perfil->vinculoLegivel(),
            'rastreios_concluidos' => $perfil->testSessions()->where('status', 'completed')->count(),
        ];
    }
}
