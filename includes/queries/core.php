<?php
/**
 * Modul queries: core — di-include dari includes/queries.php.
 */

function q_all($sql, $params = [])
{
    $db = db();
    if ($db === null) {
        return null;
    }
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $baris = $stmt->fetchAll();
        return $baris ?: [];
    } catch (Throwable $e) {
        error_log('q_all gagal: ' . $e->getMessage() . ' | SQL: ' . substr((string) $sql, 0, 200));
        return null;
    }
}

/** SELECT satu nilai. null = gagal. */

function q_col($sql, $params = [])
{
    $db = db();
    if ($db === null) {
        return null;
    }
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $nilai = $stmt->fetchColumn();
        return $nilai === false ? null : $nilai;
    } catch (Throwable $e) {
        error_log('q_col gagal: ' . $e->getMessage() . ' | SQL: ' . substr((string) $sql, 0, 200));
        return null;
    }
}

/** INSERT/UPDATE/DELETE. true = sukses, false = gagal. */

function q_exec($sql, $params = [])
{
    $db = db();
    if ($db === null) {
        return false;
    }
    try {
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } catch (Throwable $e) {
        error_log('q_exec gagal: ' . $e->getMessage() . ' | SQL: ' . substr((string) $sql, 0, 200));
        return false;
    }
}

/* ==================================================================
 * 1. DASHBOARD OPERATOR — tab 1: pendaftar + akun WA
 * ================================================================== */


function hapus_berkas_upload($pathRelatif)
{
    $pathRelatif = (string) $pathRelatif;
    $abs = null;
    /* Path lama (public assets). */
    $pos = strpos($pathRelatif, '/assets/img/uploads/');
    if ($pos !== false) {
        $abs = dirname(__DIR__, 2) . substr($pathRelatif, $pos);
    }
    /* Path baru (private storage). */
    if ($abs === null && strpos($pathRelatif, 'storage/dokumen/') === 0) {
        $abs = dirname(__DIR__, 2) . '/' . $pathRelatif;
    }
    if ($abs === null || !is_file($abs)) {
        return false;
    }
    return @unlink($abs);
}

/**
 * Update biodata pendaftar (dikunci setelah TERVERIFIKASI/DITOLAK).
 */
