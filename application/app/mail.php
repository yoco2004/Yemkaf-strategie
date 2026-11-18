<?php
/*
 * Envoi d'e-mails sans bibliothèque externe.
 * - Si 'smtp_host' est rempli dans la configuration : envoi SMTP authentifié (TLS sur 587 ou SSL sur 465),
 *   par exemple avec la boîte de l'hébergeur ou le SMTP de Brevo.
 * - Sinon : fonction mail() de l'hébergeur.
 * - 'journal_seulement' => true : rien n'est envoyé, les e-mails sont écrits dans storage/logs/mails.log (essais).
 */
declare(strict_types=1);

function envoyer_mail(string $a, string $sujet, string $texte, ?string $html = null): bool
{
    $m = cfg('mail') + ['de' => 'outils@yemkaf.fr', 'de_nom' => "Yem'kaf Outils", 'smtp_host' => '', 'smtp_port' => 587,
        'smtp_user' => '', 'smtp_pass' => '', 'smtp_secure' => 'tls', 'journal_seulement' => false];
    $frontiere = 'yk' . bin2hex(random_bytes(8));
    $entetes = [
        'Date: ' . date('r'),
        'From: ' . encoder_entete((string) $m['de_nom']) . ' <' . $m['de'] . '>',
        'To: <' . $a . '>',
        'Subject: ' . encoder_entete($sujet),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', (string) $m['de'])[1] ?? 'yemkaf.fr') . '>',
        'MIME-Version: 1.0',
    ];
    if ($html !== null) {
        $entetes[] = 'Content-Type: multipart/alternative; boundary="' . $frontiere . '"';
        $corps = "--{$frontiere}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texte)) . "--{$frontiere}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode('<div style="font-family:Arial,sans-serif;font-size:15px;color:#1c2b23">' . $html . '</div>')) . "--{$frontiere}--\r\n";
    } else {
        $entetes[] = 'Content-Type: text/plain; charset=UTF-8';
        $entetes[] = 'Content-Transfer-Encoding: base64';
        $corps = chunk_split(base64_encode($texte));
    }
    if (!empty($m['journal_seulement'])) {
        @file_put_contents(stockage('logs/mails.log'), "==== " . date('c') . " → {$a}\nSujet : {$sujet}\n{$texte}\n\n", FILE_APPEND);
        return true;
    }
    try {
        if (!empty($m['smtp_host'])) return smtp_envoyer($m, $a, implode("\r\n", $entetes) . "\r\n\r\n" . $corps);
        $sansTo = array_values(array_filter($entetes, function ($l) { return !str_starts_with($l, 'To:') && !str_starts_with($l, 'Subject:'); }));
        $ok = mail($a, encoder_entete($sujet), $corps, implode("\r\n", $sansTo), '-f' . $m['de']);
        if (!$ok) journal_erreur('mail() a échoué pour ' . $a);
        return $ok;
    } catch (Throwable $e) {
        journal_erreur('Envoi d’e-mail : ' . $e->getMessage());
        return false;
    }
}

function encoder_entete(string $s): string { return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }

function smtp_envoyer(array $m, string $a, string $message): bool
{
    $secure = strtolower((string) $m['smtp_secure']);
    $hote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $m['smtp_host'] . ':' . (int) $m['smtp_port'];
    $f = @stream_socket_client($hote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
    if (!$f) throw new RuntimeException("Connexion SMTP impossible ($errstr)");
    stream_set_timeout($f, 20);
    $lire = function () use ($f): string {
        $rep = '';
        while (($l = fgets($f, 515)) !== false) { $rep .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $rep;
    };
    $cmd = function (string $c, array $attendus) use ($f, $lire): string {
        fwrite($f, $c . "\r\n");
        $r = $lire();
        if (!in_array((int) substr($r, 0, 3), $attendus, true)) throw new RuntimeException('SMTP : réponse inattendue à « ' . explode(' ', $c)[0] . ' » : ' . trim($r));
        return $r;
    };
    $accueil = $lire();
    if ((int) substr($accueil, 0, 3) !== 220) throw new RuntimeException('SMTP : accueil inattendu ' . trim($accueil));
    $domaine = explode('@', (string) $m['de'])[1] ?? 'localhost';
    $cmd('EHLO ' . $domaine, [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($f, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('SMTP : échec du chiffrement TLS');
        $cmd('EHLO ' . $domaine, [250]);
    }
    if ($m['smtp_user'] !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string) $m['smtp_user']), [334]);
        $cmd(base64_encode((string) $m['smtp_pass']), [235]);
    }
    $cmd('MAIL FROM:<' . $m['de'] . '>', [250]);
    $cmd('RCPT TO:<' . $a . '>', [250, 251]);
    $cmd('DATA', [354]);
    $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $message));
    $cmd($message . "\r\n.", [250]);
    fwrite($f, "QUIT\r\n");
    fclose($f);
    return true;
}
