<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Spec 7: only JPG, PNG, WebP up to 5 MB, real type checked from the bytes, re-encoded
 * with GD (strips metadata and any hidden payload), at most 1600 px, random name,
 * private disk. Never reachable by URL.
 */
class PaymentProofService
{
    public const DISK = 'local';
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE = 1600;
    public const METHODS = ['stc_pay', 'bank_transfer', 'other'];

    public function store(Payment $payment, UploadedFile $file, string $method, ?string $reference = null): Payment
    {
        if (! extension_loaded('gd')) {
            throw new DomainException('Screenshot upload is not available right now. Please send it on WhatsApp.');
        }

        $path = (string) $file->getRealPath();

        if ($path === '' || (int) $file->getSize() > self::MAX_BYTES) {
            throw new DomainException('The screenshot must be a JPG, PNG or WebP image up to 5 MB.');
        }

        $info = @getimagesize($path);
        $type = $info[2] ?? null;

        if ($info === false || ! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new DomainException('The screenshot must be a JPG, PNG or WebP image up to 5 MB.');
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];

        if ($width < 1 || $height < 1 || $width * $height > 40_000_000) {
            throw new DomainException('That image is too large. Please send a normal screenshot.');
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $source) {
            throw new DomainException('We could not read that image. Please try another screenshot.');
        }

        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));

        // Flatten onto white so transparent PNGs do not turn black.
        $canvas = imagecreatetruecolor($newW, $newH);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $bytes = (string) ob_get_clean();

        if ($bytes === '') {
            throw new DomainException('We could not process that image. Please try another screenshot.');
        }

        $hash = hash_file('sha256', $path);
        $name = 'payment-proofs/'.Str::uuid()->toString().'.jpg';

        return DB::transaction(function () use ($payment, $method, $reference, $bytes, $name, $hash): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! in_array($locked->status, [PaymentStatus::AwaitingProof, PaymentStatus::Rejected], true)) {
                throw new DomainException('This payment cannot take a new screenshot.');
            }

            Storage::disk(self::DISK)->put($name, $bytes);

            if (filled($locked->proof_path)) {
                Storage::disk(self::DISK)->delete($locked->proof_path);
            }

            // The same screenshot used on another payment is flagged for staff, not blocked.
            $duplicate = Payment::query()
                ->where('proof_hash', $hash)
                ->where('id', '!=', $locked->id)
                ->value('id');

            $payload = (array) ($locked->payload ?? []);
            if ($duplicate) {
                $payload['duplicate_of'] = (int) $duplicate;
            } else {
                unset($payload['duplicate_of']);
            }

            $locked->forceFill([
                'method' => in_array($method, self::METHODS, true) ? $method : 'other',
                'proof_path' => $name,
                'proof_uploaded_at' => now(),
                'proof_hash' => $hash,
                'customer_reference' => $reference !== null && trim($reference) !== '' ? Str::limit(trim($reference), 100, '') : null,
                'status' => PaymentStatus::AwaitingVerification,
                'reject_reason' => null,
                'payload' => $payload === [] ? null : $payload,
            ])->save();

            return $locked;
        });
    }

    /** Deletes the stored file and forgets its path (the hash stays for duplicate checks). */
    public function purge(Payment $payment): void
    {
        if (filled($payment->proof_path)) {
            Storage::disk(self::DISK)->delete($payment->proof_path);
        }

        $payment->forceFill(['proof_path' => null])->save();
    }
}