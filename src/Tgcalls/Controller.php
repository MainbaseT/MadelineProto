<?php declare(strict_types=1);

/**
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2025 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto;

use Amp\ByteStream\BufferedReader;
use Amp\ByteStream\ReadableBuffer;
use danog\MadelineProto\MTProtoTools\Crypt;
use danog\MadelineProto\VoIP\SignalingProtocolVersion;
use Webrtc\ICE\RTCIceCandidate;
use Webrtc\Webrtc\RTCPeerConnection;

/** @internal */
final class Controller {

    private RTCPeerConnection $peerConnection;
    public function __construct(
        private readonly string $authKey,
        private readonly bool $outgoing,
        private readonly SignalingProtocolVersion $tgcallsVersion,
        private readonly MTProto $API,
    )
    {
        $this->peerConnection = new RTCPeerConnection();
    }


    private const SIGNALING_MIN_SIZE = 21;
    private const SIGNALING_MAX_SIZE = 128 * 1024 * 1024;

    private const SINGLE_MESSAGE_PACKET_BIT = 1 << 31;
    private const MESSAGE_REQUIRES_ACK_SEQ_BIT = 1 << 30;

    private const MAX_ALLOWED_COUNTER = ~self::SINGLE_MESSAGE_PACKET_BIT
        & ~self::MESSAGE_REQUIRES_ACK_SEQ_BIT;

    public const ACK_ID = 255;
    public const EMPTY_ID = 254;
    public const CUSTOM_ID = 127;

    private static function gunzip(string $data): string
    {
        if (\strlen($data) < 2) {
            return $data;
        }

        if (($data[0] == \chr(0x1f) && $data[1] == \chr(0x8b)) || ($data[0] == \chr(0x78) && $data[1] == \chr(0x9c))) {
            return gzdecode($data);
        }
        return $data;

    }

    public function onSignaling(string $data): void
    {
        if ($this->tgcallsVersion === null) {
            throw new Exception('Protocol version is not set!');
        }
        if (\strlen($data) < self::SIGNALING_MIN_SIZE || \strlen($data) > self::SIGNALING_MAX_SIZE) {
            throw new Exception('Invalid signaling size!');
        }
        $message_key = substr($data, 0, 16);
        $data = substr($data, 16);
        [$aes_key, $aes_iv, $x] = Crypt::voipKdf($message_key, $this->authKey, $this->outgoing, false);
        $packet = Crypt::ctrEncrypt($data, $aes_key, $aes_iv);

        if ($message_key != substr(hash('sha256', substr($this->authKey, 88 + $x, 32).$packet, true), 8, 16)) {
            throw new Exception('msg_key mismatch!');
        }
        if (\strlen($packet) < self::SIGNALING_MIN_SIZE || \strlen($packet) > self::SIGNALING_MAX_SIZE) {
            throw new Exception('Invalid signaling size!');
        }

        if ($this->tgcallsVersion->supportsCompression()) {
            $packet = self::gunzip($packet);

            $seq = unpack('N', substr($packet, 0, 4))[1];

            $this->onSignalingMessage($this->deserializeRtc(null, substr($packet, 4)));
            return;
        }

        $packet = new BufferedReader(new ReadableBuffer($packet));

        $first = true;
        while ($packet->isReadable()) {
            $seq = unpack('N', $packet->readLength(4))[1];
            $messageRequiresAck = (bool) ($seq & self::MESSAGE_REQUIRES_ACK_SEQ_BIT);
            $singlePacketFlag = (bool) ($seq & self::SINGLE_MESSAGE_PACKET_BIT);

            if (!$first && $singlePacketFlag) {
                throw new Exception('Single packet flag can only be set on first message!');
            }

            $type = \ord($packet->readLength(1));
            if ($type === self::EMPTY_ID) {
                if (!$first) {
                    throw new Exception('Empty packet can only be first message!');
                }
            } elseif ($type === self::ACK_ID) {
                // todo ack $seq (contains my seq to be acked)
            } else {
                $length = unpack('N', $packet->readLength(4))[1];
                if ($length > 1024 * 1024) {
                    throw new Exception('Invalid signaling message length!');
                }
                $str = $packet->readLength($length);
                if (\strlen($str) !== $length) {
                    throw new Exception('Signaling message is shorter than expected!');
                }

                $this->onSignalingMessage($this->deserializeRtc($type, $str));
            }
            $first = false;
        }

    }
    private function onSignalingMessage(array $message): void
    {
        if ($this->tgcallsVersion->isJson()) {
            $this->onSignalingMessageJson($message);
            return;
        }
    }

    private function onSignalingMessageJson(array $message): void
    {
        $type = $message['@type'];
        if ($type === 'Candidates') {
            foreach ($message['candidates'] as ['sdpString' => $sdp]) {
                $this->peerConnection->addIceCandidate(RTCIceCandidate::parseSDP($sdp));
            }
            return;
        }
        var_dump($message);
        readline();
    }
    private function deserializeRtc(?int $type, string $buffer): array
    {
        if ($this->tgcallsVersion->isJson()) {
            return json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
        }
        $buffer = new BufferedReader(new ReadableBuffer($buffer));
        switch ($type) {
            case 1:
                $candidates = [];
                for ($x = \ord($buffer->readLength(1)); $x > 0; $x--) {
                    $candidates []= self::readString($buffer);
                }
                return [
                    '_' => 'candidatesList',
                    'ufrag' => self::readString($buffer),
                    'pwd' => self::readString($buffer),
                ];
            case 2:
                $formats = [];
                for ($x = \ord($buffer->readLength(1)); $x > 0; $x--) {
                    $name = self::readString($buffer);
                    $parameters = [];
                    for ($x = \ord($buffer->readLength(1)); $x > 0; $x--) {
                        $key = self::readString($buffer);
                        $value = self::readString($buffer);
                        $parameters[$key] = $value;
                    }
                    $formats[]= [
                        'name' => $name,
                        'parameters' => $parameters,
                    ];
                }
                return [
                    '_' => 'videoFormats',
                    'formats' => $formats,
                    'encoders' => \ord($buffer->readLength(1)),
                ];
            case 3:
                return ['_' => 'requestVideo'];
            case 4:
                $state = \ord($buffer->readLength(1));
                return ['_' => 'remoteMediaState', 'audio' => $state & 0x01, 'video' => ($state >> 1) & 0x03];
            case 5:
                return ['_' => 'audioData', 'data' => self::readBuffer($buffer)];
            case 6:
                return ['_' => 'videoData', 'data' => self::readBuffer($buffer)];
            case 7:
                return ['_' => 'unstructuredData', 'data' => self::readBuffer($buffer)];
            case 8:
                return ['_' => 'videoParameters', 'aspectRatio' => unpack('V', $buffer->readLength(4))[1]];
            case 9:
                return ['_' => 'remoteBatteryLevelIsLow', 'isLow' => (bool) \ord($buffer->readLength(1))];
            case 10:
                $lowCost = (bool) \ord($buffer->readLength(1));
                $isLowDataRequested = (bool) \ord($buffer->readLength(1));
                return ['_' => 'remoteNetworkStatus', 'lowCost' => $lowCost, 'isLowDataRequested' => $isLowDataRequested];
        }
        return ['_' => 'unknown', 'type' => $type];
    }
    private static function readString(BufferedReader $buffer): string
    {
        /** @psalm-suppress InvalidArgument */
        return $buffer->readLength(\ord($buffer->readLength(1)));
    }
    private static function readBuffer(BufferedReader $buffer): string
    {
        return $buffer->readLength(unpack('n', $buffer->readLength(2))[1]);
    }

}