<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

/**
 * The QR a host prints or posts to send people to one of their pages.
 *
 * One look for all of them (pine on white, high error correction because it gets scanned off a screen across a
 * room as often as off a flyer), so a trip's code and a host page's code are plainly from the same place.
 * The check-in pass has its own, in PassQrController: that one is read at a trailhead and wants maximum contrast.
 */
class Qr
{
    public static function png(string $data): ResultInterface
    {
        return (new Builder(
            writer: new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 600,
            margin: 24,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(44, 56, 38),   // pine
            backgroundColor: new Color(255, 255, 255),
        ))->build();
    }
}
