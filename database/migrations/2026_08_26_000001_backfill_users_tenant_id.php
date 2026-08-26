<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Preenche users.tenant_id para os registros criados enquanto o campo era
 * descartado por mass assignment (User tinha $fillable e $guarded juntos, e
 * 'tenant_id' ficava fora dos dois — ver App\Models\User::createWithRole).
 *
 * Sem isto, todo usuario criado por checkout/painel ficou com tenant_id NULL e
 * so aparecia no painel por causa do orWhereNull() em AlunosController.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Admin e infoprodutor sao o proprio tenant.
        DB::table('users')
            ->whereNull('tenant_id')
            ->whereIn('role', ['admin', 'infoprodutor'])
            ->update(['tenant_id' => DB::raw('id')]);

        // 2) Demais usuarios: deriva o tenant dos produtos que a pessoa possui.
        //    Vale tanto para instalacao com um unico tenant quanto com varios.
        if (DB::getSchemaBuilder()->hasTable('product_user')) {
            DB::table('users')
                ->whereNull('users.tenant_id')
                ->join('product_user', 'product_user.user_id', '=', 'users.id')
                ->join('products', 'products.id', '=', 'product_user.product_id')
                ->whereNotNull('products.tenant_id')
                ->groupBy('users.id')
                ->select('users.id', DB::raw('MIN(products.tenant_id) as derived_tenant_id'))
                ->orderBy('users.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('users')
                            ->where('id', $row->id)
                            ->whereNull('tenant_id')
                            ->update(['tenant_id' => $row->derived_tenant_id]);
                    }
                });
        }

        // 3) Sobrou quem nunca comprou nada (lead, cadastro manual). So da para
        //    atribuir com seguranca se a instalacao tiver um unico tenant —
        //    com varios, chutar misturaria a base de clientes diferentes.
        $tenants = DB::table('users')
            ->whereIn('role', ['admin', 'infoprodutor'])
            ->whereNotNull('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        if ($tenants->count() === 1) {
            DB::table('users')
                ->whereNull('tenant_id')
                ->update(['tenant_id' => (int) $tenants->first()]);
        }
    }

    public function down(): void
    {
        // Sem rollback: nao ha como distinguir o que era NULL por bug do que
        // seria NULL legitimamente, e reverter reabriria o vazamento.
    }
};
