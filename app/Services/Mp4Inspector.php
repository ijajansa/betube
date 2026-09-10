<?php

namespace App\Services;

/** Conservative ISO BMFF inspection for the no-FFmpeg fallback; never trusts a MIME label. */
class Mp4Inspector
{
    public function inspect(string $path): ?array
    {
        $stream = @fopen($path, 'rb');
        if (! is_resource($stream)) {
            return null;
        }
        $result = ['codecs' => [], 'duration' => 0.0, 'width' => 0, 'height' => 0, 'media' => false, 'brand' => false];
        try {
            $this->boxes($stream, 0, filesize($path), $result, 0);
        } finally {
            fclose($stream);
        }
        $codecs = $result['codecs'];
        $compatible = $result['brand'] && $result['media'] && $result['duration'] > 0
            && $result['width'] > 0 && $result['height'] > 0
            && count(array_intersect($codecs, ['avc1', 'avc3'])) > 0
            && count(array_diff($codecs, ['avc1', 'avc3', 'mp4a', 'mp3 '])) === 0;

        return $compatible ? array_intersect_key($result, array_flip(['duration', 'width', 'height'])) + ['compatible_mp4' => true] : null;
    }

    private function boxes($stream, int $start, int $end, array &$result, int $depth): void
    {
        if ($depth > 8) {
            return;
        }
        $offset = $start;
        $count = 0;
        while ($offset + 8 <= $end && ++$count <= 10000) {
            fseek($stream, $offset);
            $header = fread($stream, 8);
            if (strlen($header) !== 8) {
                return;
            }
            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            $headerSize = 8;
            if ($size === 1) {
                $extended = fread($stream, 8);
                if (strlen($extended) !== 8) {
                    return;
                }
                $parts = unpack('Nhigh/Nlow', $extended);
                $size = $parts['high'] * 4294967296 + $parts['low'];
                $headerSize = 16;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }
            if ($size < $headerSize || $size > $end - $offset) {
                return;
            }
            $payloadSize = (int) $size - $headerSize;
            $payloadStart = $offset + $headerSize;
            if (in_array($type, ['moov', 'trak', 'mdia', 'minf', 'stbl'], true)) {
                $this->boxes($stream, $payloadStart, (int) ($offset + $size), $result, $depth + 1);
            } elseif ($type === 'mdat' && $payloadSize > 0) {
                $result['media'] = true;
            } elseif (in_array($type, ['ftyp', 'stsd', 'mdhd', 'tkhd'], true) && $payloadSize <= 1048576) {
                fseek($stream, $payloadStart);
                $payload = $payloadSize ? fread($stream, $payloadSize) : '';
                if ($type === 'ftyp' && strlen($payload) >= 8) {
                    $result['brand'] = in_array(substr($payload, 0, 4), ['isom', 'iso2', 'mp41', 'mp42', 'avc1', 'M4V '], true);
                } elseif ($type === 'tkhd' && strlen($payload) >= 84) {
                    $dimensions = unpack('Nwidth/Nheight', substr($payload, -8));
                    $result['width'] = max($result['width'], (int) ($dimensions['width'] / 65536));
                    $result['height'] = max($result['height'], (int) ($dimensions['height'] / 65536));
                } elseif ($type === 'mdhd' && strlen($payload) >= 24) {
                    $version = ord($payload[0]);
                    if ($version === 0) {
                        $time = unpack('Nscale/Nduration', substr($payload, 12, 8));
                        $result['duration'] = max($result['duration'], $time['scale'] ? $time['duration'] / $time['scale'] : 0);
                    } elseif ($version === 1 && strlen($payload) >= 36) {
                        $time = unpack('Nscale/Nhigh/Nlow', substr($payload, 20, 12));
                        $result['duration'] = max($result['duration'], $time['scale'] ? ($time['high'] * 4294967296 + $time['low']) / $time['scale'] : 0);
                    }
                } elseif ($type === 'stsd' && strlen($payload) >= 8) {
                    $entries = unpack('N', substr($payload, 4, 4))[1];
                    $position = 8;
                    for ($i = 0; $i < min($entries, 64); $i++) {
                        if ($position + 8 > strlen($payload)) {
                            $result['codecs'][] = 'invalid';
                            break;
                        }
                        $entrySize = unpack('N', substr($payload, $position, 4))[1];
                        if ($entrySize < 8 || $position + $entrySize > strlen($payload)) {
                            $result['codecs'][] = 'invalid';
                            break;
                        }
                        $result['codecs'][] = substr($payload, $position + 4, 4);
                        $position += $entrySize;
                    }
                }
            }
            $offset += (int) $size;
        }
    }
}
