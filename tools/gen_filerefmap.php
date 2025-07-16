<?php declare(strict_types=1);

use danog\MadelineProto\Settings\TLSchema;
use danog\MadelineProto\TL\TL;
use danog\MadelineProto\TL\TLInterface;
use Webmozart\Assert\Assert;

require 'vendor/autoload.php';

$TL = new TL(null);
$TL->init(new TLSchema);

$final = [];
$locations = [];

final class TLContext
{
    public function __construct(
        private readonly TLInterface $tl,
        private readonly string $position,
    ) {
    }

    public function getTypeAtPosition(ExtractFromHereOp|ExtractFromRootOp $path): string
    {
        if ($path instanceof ExtractFromHereOp) {
            Assert::eq($this->position, $path->path[0], 'Path position does not match current position');
        }
        $path = $path->path;
        $idx = 0;
        $type = null;
        do {
            if ($type !== null) {
                $_ = self::getConstructorsOfType($this->tl, $type)[$path[$idx]] ?? null;
                Assert::notNull($_, "Could not find type {$path[$idx]} for $type, path: " . json_encode($path));
            }
            $constructor = $this->tl->getConstructors()->findByPredicate($path[$idx]);
            if ($constructor === false) {
                $constructor = $this->tl->getMethods()->findByMethod($path[$idx]);
            }
            Assert::notFalse($constructor, "Constructor or method not found for path: " . json_encode($path));

            $idx++;
            $type = null;
            foreach ($constructor['params'] as $param) {
                if ($param['name'] === $path[$idx]) {
                    $type = $param['type'];
                }
            }
            $n = $constructor['predicate'] ?? $constructor['name'];
            Assert::notNull($type, "Parameter {$path[$idx]} not found in constructor or method $n: " . json_encode($path));
        } while (++$idx < count($path));

        return $type;
    }
    public static function getConstructorsOfType(TLInterface $tl, string $type): array
    {
        $constructors = [];
        foreach ($tl->getConstructors()->by_id as $constructor) {
            if ($constructor['type'] === $type) {
                $constructors[$constructor['predicate']] = true;
            }
        }
        foreach ($tl->getMethods()->by_id as $method) {
            if ($method['type'] === $type) {
                $constructors[$method['method']] = false;
            }
        }
        Assert::notEmpty($constructors, "No constructors found for type: $type");
        return $constructors;
    }
}

interface Op
{
    public function build(TLContext $tl): array;
}

final class CopyMethodCallOp implements Op
{
    public function build(TLContext $tl): array
    {
        return ['op' => 'copyMethodCall'];
    }
}

final class ThemeFormatOp implements Op
{
    public function __construct()
    {
    }

    public function build(TLContext $tl): array
    {
        return [
            'op' => 'themeFormat',
        ];
    }
}
final class ExtractFromHereOp implements Op
{
    /** @var string[] */
    public readonly array $path;
    public function __construct(string ...$path)
    {
        $this->path = $path;
    }

    public function extend(string ...$path): self
    {
        return new self(...$this->path, ...$path);
    }

    public function build(TLContext $tl): array
    {
        $tl->getTypeAtPosition($this);
        return [
            'op' => 'extractFromHere',
            'path' => $this->path,
        ];
    }
}

final class ExtractFromRootOp implements Op
{
    /** @var string[] */
    public readonly array $path;
    public function __construct(string ...$path)
    {
        $this->path = $path;
    }

    public function extend(string ...$path): self
    {
        return new self(...$this->path, ...$path);
    }

    public function build(TLContext $tl): array
    {
        $tl->getTypeAtPosition($this);
        return [
            'op' => 'extractFromRoot',
            'path' => $this->path,
        ];
    }
}

final class GetInputPeerOp implements Op
{
    public function __construct(private readonly ExtractFromHereOp|ExtractFromRootOp $path)
    {
    }

    public function build(TLContext $tl): array
    {
        $type = $tl->getTypeAtPosition($this->path);
        if ($type === 'InputPeer') {
            return [
                'op' => 'identity',
                'from' => $this->path,
            ];
        }
        Assert::eq($type, 'Peer', "Expected type 'Peer' at position {$this->path->path[0]} but got '$type'");
        return [
            'op' => 'getInputPeer',
            'from' => $this->path,
        ];
    }
}
final class GetInputUserOp implements Op
{
    public function __construct(private readonly ExtractFromHereOp|ExtractFromRootOp $path)
    {
    }

    public function build(TLContext $tl): array
    {
        $type = $tl->getTypeAtPosition($this->path);
        if ($type === 'InputUser') {
            return [
                'op' => 'identity',
                'from' => $this->path,
            ];
        }
        if ($type === 'long') {
            return [
                'op' => 'getInputUserById',
                'from' => $this->path,
            ];
        }
        Assert::eq($type, 'User', "Expected type 'User' at position {$this->path->path[0]} but got '$type'");
        return [
            'op' => 'getInputUser',
            'from' => $this->path,
        ];
    }
}
final class GetInputChannelOp implements Op
{
    public function __construct(private readonly ExtractFromHereOp|ExtractFromRootOp $path)
    {
    }

    public function build(TLContext $tl): array
    {
        $type = $tl->getTypeAtPosition($this->path);
        if ($type === 'InputChannel') {
            return [
                'op' => 'identity',
                'from' => $this->path,
            ];
        }
        if ($type === 'long') {
            return [
                'op' => 'getInputChannelById',
                'from' => $this->path,
            ];
        }
        Assert::eq($type, 'Channel', "Expected type 'Channel' at position {$this->path->path[0]} but got '$type'");
        return [
            'op' => 'getInputChannel',
            'from' => $this->path,
        ];
    }
}

final class ArrayOp implements Op
{
    /** @var Op[] */
    private readonly array $values;
    public function __construct(Op ...$values)
    {
        $this->values = $values;
    }

    public function build(TLContext $tl): array
    {
        $arr = [];
        foreach ($this->values as $key => $value) {
            $arr[$key] = $value->build($tl);
        }
        return [
            'op' => 'array',
            'value' => $arr,
        ];
    }
}

final class LiteralOp implements Op
{
    public function __construct(private readonly mixed $value)
    {
    }

    public function build(TLContext $tl): array
    {
        return [
            'op' => 'literal',
            'value' => $this->value,
        ];
    }
}

final class GetMessageOp implements Op
{
    public function __construct(
        private readonly Op $peer,
        private readonly Op $id,
    ) {
    }

    public function build(TLContext $tl): array
    {
        return [
            'op' => 'get_message',
            'peer' => $this->peer->build($tl),
            'id' => $this->id->build($tl),
        ];
    }
}

final class CallOp implements Op
{
    /** @param Op[] $args */
    public function __construct(
        private readonly string $method,
        private readonly array $args
    ) {
    }

    public static function simple(string $method, string $constructor, array $args): self
    {
        $final = [];
        foreach ($args as $from => $to) {
            if (!$to instanceof Op) {
                $to = new ExtractFromHereOp($constructor, $to);
            }
            $final[$from] = $to;
        }
        return new CallOp($method, $final);
    }

    public function build(TLContext $tl): array
    {
        $final = [];
        foreach ($this->args as $from => $to) {
            $final[$from] = $to->build($tl);
        }
        return [
            'op' => 'call',
            'method' => $this->method,
            'args' => $final,
        ];
    }
}
final class ConstructorOp implements Op
{
    /** @param Op[] $args */
    public function __construct(
        private readonly string $constructor,
        private readonly array $args
    ) {
    }

    public function build(TLContext $tl): array
    {
        $final = [];
        foreach ($this->args as $from => $to) {
            $final[$from] = $to->build($tl);
        }
        return [
            'op' => 'constructor',
            'constructor' => $this->constructor,
            'args' => $final,
        ];
    }
}

$recurse = static function (string $type, array $stack = []) use ($TL, &$recurse, &$final, &$locations): void {
    if ($type === 'Message') {
        foreach (TLContext::getConstructorsOfType($TL, $type) as $constructor => $_) {
            if ($constructor === 'messageEmpty') {
                continue;
            }
            $locations[$constructor] = new GetMessageOp(
                new ExtractFromHereOp($constructor, 'peer_id'),
                new ExtractFromHereOp($constructor, 'id'),
            );
        }
        return;
    }
    if ($type === 'WebPage') {
        $locations['webPage'] = CallOp::simple('messages.getWebPage', 'webPage', ['url' => 'url']);
        return;
    }
    if ($type === 'BotApp') {
        $locations['botApp'] = CallOp::simple('messages.getBotApp', 'botApp', [
            'app' => new ConstructorOp(
                'inputBotAppID',
                [
                    'id' => new ExtractFromHereOp('botApp', 'id'),
                    'access_hash' => new ExtractFromHereOp('botApp', 'access_hash'),
                ]
            ),
            'hash' => new LiteralOp(0),
        ]);
        return;
    }
    if ($type === 'BotInfo') {
        $locations['botInfo'] = new CallOp(
            'users.getFullUser',
            ['id' => new GetInputUserOp(new ExtractFromHereOp('botInfo', 'user_id'))],
        );
        return;
    }
    if ($type === 'StoryItem') {
        $locations['storyItem'] = new CallOp('stories.getStoriesByID', [
            'id' => new ArrayOp(new ExtractFromHereOp('storyItem', 'id')),
            'peer' => new GetInputPeerOp(new ExtractFromHereOp('storyItem', 'from_id')),
        ]);
        return;
    }
    if ($type === 'messages.SponsoredMessages') {
        $locations['messages.getSponsoredMessages'] = new CopyMethodCallOp();
        return;
    }
    if ($type === 'ChannelAdminLogEvent') {
        $locations['channelAdminLogEvent'] = new CallOp(
            'channels.getAdminLog',
            [
                'channel' => new GetInputChannelOp(new ExtractFromRootOp('channels.getAdminLog', 'channel')),
                'max_id' => new ExtractFromHereOp('channelAdminLogEvent', 'id'),
                'min_id' => new ExtractFromHereOp('channelAdminLogEvent', 'id'),
                'limit' => new LiteralOp(1),
            ]
        );
        return;
    }
    if ($type === 'bots.PreviewInfo') {
        $locations['bots.getPreviewInfo'] = new CopyMethodCallOp();
        return;
    }
    if ($type === 'MessageExtendedMedia') {
        $locations['updateMessageExtendedMedia'] = new CallOp(
            'messages.getExtendedMedia',
            [
                'id' => new ArrayOp(new ExtractFromHereOp('updateMessageExtendedMedia', 'msg_id')),
                'peer' => new GetInputPeerOp(new ExtractFromHereOp('updateMessageExtendedMedia', 'peer')),
            ]
        );
        return;
    }
    if ($type === 'UserFull') {
        $locations['userFull'] = new CallOp(
            'users.getFullUser',
            [
                'id' => new GetInputUserOp(new ExtractFromHereOp('userFull', 'id')),
            ]
        );
        return;
    }
    if ($type === 'ChatFull') {
        $locations['chatFull'] = new CallOp(
            'messages.getFullChat',
            [
                'chat_id' => new ExtractFromHereOp('chatFull', 'id'),
            ]
        );
        $locations['channelFull'] = new CallOp(
            'channels.getFullChannel',
            [
                'channel' => new GetInputChannelOp(new ExtractFromHereOp('channelFull', 'id')),
            ]
        );
        return;
    }
    if ($type === 'help.PremiumPromo') {
        $locations['help.getPremiumPromo'] = new CopyMethodCallOp;
        return;
    }
    if ($type === 'StarsTransaction') {
        foreach (TLContext::getConstructorsOfType($TL, $type) as $constructor => $isConstructor) {
            if ($isConstructor) {
                continue;
            }
            $locations[$constructor] = new CallOp(
                'payments.getStarsTransactionByID',
                [
                    'peer' => new ExtractFromRootOp($constructor, 'peer'),
                    'id' => new ConstructorOp(
                        'inputStarsTransaction',
                        [
                            'id' => new ExtractFromHereOp($constructor, 'id'),
                            'refund' => new ExtractFromHereOp($constructor, 'refund'),
                        ]
                    ),
                ]
            );
        }
        return;
    }
    if ($type === 'AttachMenuBot') {
        $locations['attachMenuBot'] = new CallOp(
            'bots.getAttachMenuBot',
            ['bot' => new GetInputUserOp(new ExtractFromHereOp('attachMenuBot', 'bot_id'))]
        );
        return;
    }
    if ($type === 'Theme') {
        $locations['theme'] = new CallOp(
            'account.getTheme',
            [
                'theme' => new ConstructorOp(
                    'inputTheme',
                    [
                        'id' => new ExtractFromHereOp('theme', 'id'),
                        'access_hash' => new ExtractFromHereOp('theme', 'access_hash'),
                    ]
                ),
                'format' => new ThemeFormatOp(),
            ]
        );
        return;
    }
    if ($type === 'WallPaper') {
        $locations['wallPaper'] = new CallOp(
            'account.getWallPaper',
            [
                'theme' => new ConstructorOp(
                    'inputWallPaper',
                    [
                        'id' => new ExtractFromHereOp('wallPaper', 'id'),
                        'access_hash' => new ExtractFromHereOp('wallPaper', 'access_hash'),
                    ]
                ),
            ]
        );
        return;
    }

    // Multiple variations to handle references from covers in StickerSetCovered and messages.StickerSet
    if ($type === 'StickerSet') {
        $locations['stickerSet'] = new CallOp(
            'messages.getStickerSet',
            [
                'stickerset' => new ConstructorOp(
                    'inputStickerSetID',
                    [
                        'id' => new ExtractFromHereOp('stickerSet', 'id'),
                        'access_hash' => new ExtractFromHereOp('stickerSet', 'access_hash'),
                        'hash' => new LiteralOp(0),
                    ],
                ),
            ]
        );
        return;
    }
    if ($type === 'StickerSetCovered') {
        foreach (['stickerSetMultiCovered', 'stickerSetFullCovered'] as $c) {
            $locations[$c] = new CallOp(
                'messages.getStickerSet',
                [
                    'stickerset' => new ConstructorOp(
                        'inputStickerSetID',
                        [
                            'id' => new ExtractFromHereOp($c, 'set', 'stickerSet', 'id'),
                            'access_hash' => new ExtractFromHereOp($c, 'set', 'stickerSet', 'access_hash'),
                            'hash' => new LiteralOp(0),
                        ],
                    ),
                ]
            );
        }
        return;
    }
    if ($type === 'messages.StickerSet') {
        $locations['messages.stickerSet'] = new CallOp(
            'messages.getStickerSet',
            [
                'stickerset' => new ConstructorOp(
                    'inputStickerSetID',
                    [
                        'id' => new ExtractFromHereOp('messages.stickerSet', 'set', 'stickerSet', 'id'),
                        'access_hash' => new ExtractFromHereOp('messages.stickerSet', 'set', 'stickerSet', 'access_hash'),
                        'hash' => new LiteralOp(0),
                    ],
                ),
            ]
        );
        return;
    }
    if ($type === 'messages.SavedGifs') {
        $locations['messages.savedGifs'] = new CallOp('messages.getSavedGifs', ['hash' => new LiteralOp(0)]);
        return;
    }
    if ($type === 'account.SavedRingtones' || $type === 'account.SavedRingtone') {
        foreach (['account.savedRingtones', 'account.savedRingtoneConverted', 'account.uploadRingtone'] as $c) {
            $locations[$c] = new CallOp('account.getSavedRingtones', ['hash' => new LiteralOp(0)]);
        }
        return;
    }
    if ($type === 'RecentMeUrl') {
        $locations['recentMeUrlChatInvite'] = new CallOp(
            'messages.checkChatInvite',
            ['hash' => new ExtractFromHereOp('recentMeUrlChatInvite', 'url')],
        );
        return;
    }
    if ($type === 'messages.AvailableEffects') {
        $locations['messages.availableEffects'] = new CallOp(
            'messages.getAvailableEffects',
            ['hash' => new LiteralOp(0)],
        );
        return;
    }
    if ($type === 'messages.AvailableReactions') {
        $locations['messages.availableReactions'] = new CallOp(
            'messages.getAvailableReactions',
            ['hash' => new LiteralOp(0)],
        );
        return;
    }

    if ($type === 'payments.ResaleStarGifts' || $type === 'payments.StarGiftUpgradePreview') {
        // Ignore for now
        return;
    }
    if ($type === 'photos.Photos' || $type === 'photos.Photo') {
        // TODO: implement manually
        return;
    }
    if ($type === 'StarGift') {
        return;
    }
    if ($type === 'BotInlineResult') {
        // Ignore ephemeral inline results
        return;
    }
    if (in_array($type, [
        // Extract from document attributes
        'messages.FoundStickers',
        'messages.Stickers',
        'messages.RecentStickers',
        'messages.FavedStickers',
    ], true)) {
        // TODO!
        return;
    }

    $pos = count($stack);
    $found = false;
    foreach ([...$TL->getConstructors()->by_id, ...$TL->getMethods()->by_id] as $constructor) {
        $name = $constructor['predicate'] ?? $constructor['method'];
        foreach ($constructor['params'] as $param) {
            if ($param['type'] === $type && !in_array($name, $stack, true)) {
                $stack[$pos] = $name;
                $recurse($constructor['type'], $stack);
                $found = true;
            }
            if (isset($param['subtype'])
                && $param['subtype'] === $type
                && !in_array($name, $stack, true)
            ) {
                $stack[$pos] = $name;
                $recurse($constructor['type'], $stack);
                $found = true;
            }
        }
    }
    if (!$found) {
        if (
            (
                in_array($stack[0], ['photo', 'document'], true)
                && $stack[1] === 'game'
                && in_array(end($stack), [
                    'messages.webPagePreview',
                    'payments.starsStatus',
                    'messages.invitedUsers',
                    'payments.paymentResult',
                ], true)
            ) || array_intersect(
                [
                    'updateServiceNotification',
                    'updateShortSentMessage',
                    'updateShortMessage',
                    'updateShortChatMessage',
                ],
                $stack,
            ) || end($stack) === 'messages.webPagePreview'
            || end($stack) === 'help.appUpdate'
        ) {
            return;
        }
        $final[json_encode($stack)]= $stack;
    }
};

foreach (['Document' => 'document', 'Photo' => 'photo'] as $type => $constructor) {
    $recurse($type, [$constructor]);
}

if ($final) {
    var_dump("Have leftover reference paths!");
    var_dump(array_values($final));
    die(1);
}

foreach ($locations as $constructor => $op) {
    var_dump("Processing $constructor");
    var_dump($op = $op->build(new TLContext($TL, $constructor)));
}
