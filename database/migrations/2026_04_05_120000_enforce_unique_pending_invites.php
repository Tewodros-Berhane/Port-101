<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM invites
            WHERE id IN (
                SELECT id
                FROM (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY LOWER(email), role, COALESCE(company_id::text, 'platform')
                            ORDER BY created_at DESC, id DESC
                        ) AS row_number
                    FROM invites
                    WHERE accepted_at IS NULL
                ) ranked_invites
                WHERE ranked_invites.row_number > 1
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX invites_unique_pending_target_idx
            ON invites (LOWER(email), role, COALESCE(company_id::text, 'platform'))
            WHERE accepted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invites_unique_pending_target_idx');
    }
};
