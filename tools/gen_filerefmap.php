<?php declare(strict_types=1);

use danog\MadelineProto\FileRefExtractor\BuildMode\Ast;
use danog\MadelineProto\FileRefExtractor\Ops\ArrayOp;
use danog\MadelineProto\FileRefExtractor\Ops\CallOp;
use danog\MadelineProto\FileRefExtractor\Ops\ConstructorOp;
use danog\MadelineProto\FileRefExtractor\Ops\CopyMethodCallOp;
use danog\MadelineProto\FileRefExtractor\Ops\ExtractFromHereOp;
use danog\MadelineProto\FileRefExtractor\Ops\ExtractFromParentOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetInputChannelOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetInputPeerOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetInputUserOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetMessageOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetStickerSetFromDocumentAttributesOp;
use danog\MadelineProto\FileRefExtractor\Ops\Noop;
use danog\MadelineProto\FileRefExtractor\Ops\PrimitiveLiteralOp;
use danog\MadelineProto\FileRefExtractor\Ops\ThemeFormatOp;
use danog\MadelineProto\FileRefExtractor\TLContext;
use danog\MadelineProto\FileRefExtractor\TLWrapper;
use danog\MadelineProto\Settings\TLSchema;
use danog\MadelineProto\TL\TL;

require 'vendor/autoload.php';

$TL = new TL(null);
$TL->init(new TLSchema);

$TL = new TLWrapper($TL);
$locations = [];

foreach ($TL->getConstructorsOfType('Message') as $constructor => $_) {
    if ($constructor === 'messageEmpty') {
        continue;
    }
    $locations[$constructor][] = new GetMessageOp(
        new ExtractFromHereOp([[$constructor, 'peer_id']]),
        new ExtractFromHereOp([[$constructor, 'id']]),
        new ExtractFromHereOp([[$constructor, 'from_scheduled']]),
    );
}

foreach (['stories.StoryViewsList', 'stories.Stories', 'stories.PeerStories', 'stories.StoryReactionsList'] as $t) {
    foreach ($TL->getMethodsOfType($t) as $method => $_) {
        $locations['storyItem'][] = new CallOp(
            'stories.getStoriesByID',
            [
                'id' => new ArrayOp(new ExtractFromHereOp([['storyItem', 'id']])),
                'peer' => new GetInputPeerOp(new ExtractFromParentOp([[$method, 'peer']])),
            ]
        );
    }
}
$locations['storyItem'][] = new CallOp(
    'stories.getStoriesByID',
    [
        'id' => new ArrayOp(new ExtractFromHereOp([['storyItem', 'id']])),
        'peer' => new GetInputPeerOp(new ExtractFromParentOp([['peerStories', 'peer']])),
    ]
);

foreach (['foundStory', 'publicForwardStory', 'webPageAttributeStory'] as $c) {
    $locations[$c][] = new CallOp(
        'stories.getStoriesByID',
        [
            'id' => new ArrayOp(new ExtractFromHereOp([$c === 'webPageAttributeStory' ? [$c, 'story', null] : [$c, 'story'], ['storyItem', 'id']])),
            'peer' => new GetInputPeerOp(new ExtractFromHereOp([[$c, 'peer']])),
        ]
    );
}

$locations['webPage'][] = CallOp::simple('messages.getWebPage', 'webPage', ['url' => 'url', 'hash' => new PrimitiveLiteralOp('int', 0)]);
$locations['botApp'][] = CallOp::simple('messages.getBotApp', 'botApp', [
    'app' => new ConstructorOp(
        'inputBotAppID',
        [
            'id' => new ExtractFromHereOp([['botApp', 'id']]),
            'access_hash' => new ExtractFromHereOp([['botApp', 'access_hash']]),
        ]
    ),
    'hash' => new PrimitiveLiteralOp('long', 0),
]);
$locations['botInfo'][] = new CallOp(
    'users.getFullUser',
    ['id' => new GetInputUserOp(new ExtractFromHereOp([['botInfo', 'user_id', null]]))],
);
$locations['storyItem'][] = new CallOp('stories.getStoriesByID', [
    'id' => new ArrayOp(new ExtractFromHereOp([['storyItem', 'id']])),
    'peer' => new GetInputPeerOp(new ExtractFromHereOp([['storyItem', 'from_id', null]])),
]);
$locations['messages.getSponsoredMessages'][] = new CopyMethodCallOp('messages.getSponsoredMessages');
$locations['channelAdminLogEvent'][] = new CallOp(
    'channels.getAdminLog',
    [
        'channel' => new GetInputChannelOp(new ExtractFromParentOp([['channels.getAdminLog', 'channel']])),
        'max_id' => new ExtractFromHereOp([['channelAdminLogEvent', 'id']]),
        'min_id' => new ExtractFromHereOp([['channelAdminLogEvent', 'id']]),
        'limit' => new PrimitiveLiteralOp('int', 1),
        'q' => new PrimitiveLiteralOp('string', ''),
    ]
);
$locations['bots.getPreviewMedias'][] = new CopyMethodCallOp('bots.getPreviewMedias');
$locations['bots.getPreviewInfo'][] = new CopyMethodCallOp('bots.getPreviewInfo');
$locations['bots.addPreviewMedia'][] = new CallOp('bots.getPreviewInfo', [
    'bot' => new ExtractFromHereOp([['bots.addPreviewMedia', 'bot']]),
    'lang_code' => new ExtractFromHereOp([['bots.addPreviewMedia', 'lang_code']]),
]);
$locations['bots.editPreviewMedia'][] = new CallOp('bots.getPreviewInfo', [
    'bot' => new ExtractFromHereOp([['bots.editPreviewMedia', 'bot']]),
    'lang_code' => new ExtractFromHereOp([['bots.editPreviewMedia', 'lang_code']]),
]);

$locations['updateMessageExtendedMedia'][] = new CallOp(
    'messages.getExtendedMedia',
    [
        'id' => new ArrayOp(new ExtractFromHereOp([['updateMessageExtendedMedia', 'msg_id']])),
        'peer' => new GetInputPeerOp(new ExtractFromHereOp([['updateMessageExtendedMedia', 'peer']])),
    ]
);
$locations['userFull'][] = new CallOp(
    'users.getFullUser',
    [
        'id' => new GetInputUserOp(new ExtractFromHereOp([['userFull', 'id']])),
    ]
);
$locations['chatFull'][] = new CallOp(
    'messages.getFullChat',
    [
        'chat_id' => new ExtractFromHereOp([['chatFull', 'id']]),
    ]
);
$locations['channelFull'][] = new CallOp(
    'channels.getFullChannel',
    [
        'channel' => new GetInputChannelOp(new ExtractFromHereOp([['channelFull', 'id']])),
    ]
);
$locations['help.getPremiumPromo'][] = new CopyMethodCallOp('help.getPremiumPromo');
foreach ($TL->getMethodsOfType('payments.StarsStatus') as $method => $_) {
    $locations['starsTransaction'][] = new CallOp(
        'payments.getStarsTransactionsByID',
        [
            'peer' => new ExtractFromParentOp([[$method, 'peer']]),
            ...($method === 'payments.getStarsSubscriptions' ? [] : ['ton' => new ExtractFromParentOp([[$method, 'ton', true]])]),
            'id' => new ArrayOp(new ConstructorOp(
                'inputStarsTransaction',
                [
                    'id' => new ExtractFromHereOp([['starsTransaction', 'id']]),
                    'refund' => new ExtractFromHereOp([['starsTransaction', 'refund', true]]),
                ]
            )),
        ]
    );
}
$locations['attachMenuBot'][] = new CallOp(
    'messages.getAttachMenuBot',
    ['bot' => new GetInputUserOp(new ExtractFromHereOp([['attachMenuBot', 'bot_id']]))]
);
$locations['theme'][] = new CallOp(
    'account.getTheme',
    [
        'theme' => new ConstructorOp(
            'inputTheme',
            [
                'id' => new ExtractFromHereOp([['theme', 'id']]),
                'access_hash' => new ExtractFromHereOp([['theme', 'access_hash']]),
            ]
        ),
        'format' => new ThemeFormatOp(),
    ]
);
$locations['wallPaper'][] = new CallOp(
    'account.getWallPaper',
    [
        'wallpaper' => new ConstructorOp(
            'inputWallPaper',
            [
                'id' => new ExtractFromHereOp([['wallPaper', 'id']]),
                'access_hash' => new ExtractFromHereOp([['wallPaper', 'access_hash']]),
            ]
        ),
    ]
);

// Multiple variations to handle references from covers in StickerSetCovered and messages.StickerSet
foreach (['stickerSetMultiCovered', 'stickerSetFullCovered'] as $c) {
    $locations[$c][] = new CallOp(
        'messages.getStickerSet',
        [
            'stickerset' => new ConstructorOp(
                'inputStickerSetID',
                [
                    'id' => new ExtractFromHereOp([[$c, 'set'], ['stickerSet', 'id']]),
                    'access_hash' => new ExtractFromHereOp([[$c, 'set'], ['stickerSet', 'access_hash']]),
                ],
            ),
            'hash' => new PrimitiveLiteralOp('int', 0),
        ]
    );
}
$locations['messages.stickerSet'][] = new CallOp(
    'messages.getStickerSet',
    [
        'stickerset' => new ConstructorOp(
            'inputStickerSetID',
            [
                'id' => new ExtractFromHereOp([['messages.stickerSet', 'set'], ['stickerSet', 'id']]),
                'access_hash' => new ExtractFromHereOp([['messages.stickerSet', 'set'], ['stickerSet', 'access_hash']]),
            ],
        ),
        'hash' => new PrimitiveLiteralOp('int', 0),
    ]
);
$locations['messages.savedGifs'][] = new CallOp('messages.getSavedGifs', ['hash' => new PrimitiveLiteralOp('long', 0)]);
foreach (['account.savedRingtones', 'account.savedRingtoneConverted', 'account.uploadRingtone'] as $c) {
    $locations[$c][] = new CallOp('account.getSavedRingtones', ['hash' => new PrimitiveLiteralOp('long', 0)]);
}

$locations['recentMeUrlChatInvite'][] = new Noop('Do not store references based on chat invite links');
$locations['messages.checkChatInvite'][] = new Noop('Do not store references based on chat invite links');

$locations['messages.availableEffects'][] = new CallOp(
    'messages.getAvailableEffects',
    ['hash' => new PrimitiveLiteralOp('int', 0)],
);
$locations['messages.availableReactions'][] = new CallOp(
    'messages.getAvailableReactions',
    ['hash' => new PrimitiveLiteralOp('int', 0)],
);

$locations['photo'][] = new CallOp(
    'photos.getUserPhotos',
    [
        'user_id' => new ExtractFromParentOp([['photos.getUserPhotos', 'user_id']]),
        'offset' => new PrimitiveLiteralOp('int', -1),
        'max_id' => new ExtractFromHereOp([['photo', 'id']]),
        'limit' => new PrimitiveLiteralOp('int', 1),
    ]
);
foreach (['photos.updateProfilePhoto', 'photos.uploadProfilePhoto'] as $method) {
    $locations['photo'][] = new CallOp(
        'photos.getUserPhotos',
        [
            'user_id' => new ExtractFromParentOp(
                [[
                    $method,
                    'bot',
                    new ConstructorOp(
                        'inputUserSelf',
                        []
                    ),
                ]]
            ),
            'offset' => new PrimitiveLiteralOp('int', -1),
            'max_id' => new ExtractFromHereOp([['photo', 'id']]),
            'limit' => new PrimitiveLiteralOp('int', 1),
        ]
    );
}
$locations['photo'][] = new CallOp(
    'photos.getUserPhotos',
    [
        'user_id' => new ExtractFromParentOp(
            [['photos.uploadContactProfilePhoto', 'user_id']],
        ),
        'offset' => new PrimitiveLiteralOp('int', -1),
        'max_id' => new ExtractFromHereOp([['photo', 'id']]),
        'limit' => new PrimitiveLiteralOp('int', 1),
    ]
);
$locations['messages.getInlineBotResults'][]= new Noop('Inline bot results are ephemeral');
$locations['messages.getPreparedInlineMessage'][]= new Noop('Inline bot results are ephemeral');

$locations['messages.uploadMedia'][]= new Noop('A freshly uploaded media file will obtain a context only once it is sent to a chat');
$locations['messages.uploadImportedMedia'][]= new Noop('A freshly uploaded media file will obtain a context only once it is sent to a chat');

$locations['document'][] = new CallOp(
    'messages.getStickerSet',
    [
        'stickerset' => new GetStickerSetFromDocumentAttributesOp(new ExtractFromHereOp([['document', 'attributes']])),
        'hash' => new PrimitiveLiteralOp('int', 0),
    ]
);
$locations['messages.getDocumentByHash'][] = new CopyMethodCallOp('messages.getDocumentByHash');
$locations['updateServiceNotification'][] = new Noop('Cannot refetch service notifications');

$locations['messages.getWebPagePreview'][] = new Noop("No locations are added for the method call, as it doesn't use persistent IDs as input; the location is instead extracted from the persistent IDs in the returned WebPage object");

// Ignore these for now
foreach (['payments.ResaleStarGifts', 'payments.StarGiftUpgradePreview', 'StarGift'] as $type) {
    foreach ($TL->getConstructorsOfType($type) as $constructor => $_) {
        $locations[$constructor][] = new Noop('Contexts for star gifts are not yet implemented');
    }
}

$recurse = static function (Closure $onStackEnd, string $type, array &$stack, array &$stackTypes) use ($TL, &$recurse): void {
    if ($type === 'Update' || $type === 'Updates' || $type === 'PeerStories') {
        $onStackEnd($stack);
        return;
    }

    $pos = count($stack);
    foreach ([...$TL->tl->getConstructors()->by_id, ...$TL->tl->getMethods()->by_id] as $constructor) {
        $predicate = $constructor['predicate'] ?? $constructor['method'];
        if ($predicate === 'updateShortMessage' || $predicate === 'updateShortChatMessage' || $predicate === 'updateShortSentMessage') {
            // Assume these are converted to message constructors by the client.
            continue;
        }
        $t = $constructor['type'];
        if (isset($stackTypes[$t])) {
            continue;
        }
        $stackTypes[$t] = true;
        foreach ($constructor['params'] as $param) {
            if ((
                $param['type'] === $type ||
                (
                    isset($param['subtype'])
                    && $param['subtype'] === $type
                )
            )) {
                $stack[$pos] = [$predicate, $param['name']];
                if (isset($param['pow'])) {
                    $stack[$pos][2] = null;
                }
                $recurse($onStackEnd, $t, $stack, $stackTypes);
                unset($stack[$pos]);

            }
        }
        unset($stackTypes[$t]);
    }
    foreach ($TL->getMethodsOfType($type, true) as $method => $data) {
        $stack[$pos] = [$method, ''];
        $onStackEnd($stack);
    }
    foreach ($TL->getMethodsOfType("Vector<$type>", true) as $method => $data) {
        $stack[$pos] = [$method, ''];
        $onStackEnd($stack);
    }
    unset($stack[$pos]);
};

$fileRefs = ['Document' => 'document', 'Photo' => 'photo'];

$output = new Ast;
foreach ($locations as $constructor => $ops) {
    foreach ($ops as $idx => $op) {
        $op->build(new TLContext($TL, $output, $constructor));
    }
}
$validated = [];

$tmp = new Ast;
foreach ($fileRefs as $type => $constructor) {
    $stack = [[$constructor, 'file_reference']];
    $stackTypes = [$type => true];
    $recurse(
        static function (array $stack) use ($locations, $TL, $tmp, &$validated): void {
            foreach ([false, true] as $ignoreFlagged) {
                $slice = [];
                $hadAll = true;
                $hadAny = false;
                $top = end($stack)[0];
                for ($x = count($stack)-1; $x >= 0; $x--) {
                    $pair = $stack[$x];
                    foreach ($locations[$pair[0]] ?? [] as $op) {
                        $normalized = $op->normalize($slice, $pair[0], $ignoreFlagged);
                        if ($normalized === null) {
                            $hadAll = false;
                            continue;
                        }
                        $hadAny = true;
                        $normalized->build(new TLContext($TL, $tmp, $top));
                        $validated[$pair[0]][spl_object_id($op)] = $op;
                    }
                    $slice[] = $pair;
                }
                if (!$ignoreFlagged && !$hadAny) {
                    throw new AssertionError("Uncovered path: " . json_encode($stack));
                }
                if ($ignoreFlagged && !$hadAll) {
                    if ($slice[0][0] === 'updateStory') {
                        // This has the peer flag set.
                        continue;
                    }
                    foreach ($slice as [$cons]) {
                        if ($cons === 'webPageAttributeStory') {
                            continue 2;
                        }
                    }
                    throw new AssertionError("Uncovered path (didn't have at least one unflagged context): " . json_encode($stack));
                }
            }
        },
        $type,
        $stack,
        $stackTypes,
    );
}

$diff = [];
foreach ($locations as $constructor => $ops) {
    if (isset($validated[$constructor])) {
        $d = array_udiff($ops, $validated[$constructor], static fn ($a, $b) => spl_object_id($a) <=> spl_object_id($b));
        if ($d) {
            $diff[$constructor] = $d;
        }
        continue;
    }
    $diff[$constructor] = $ops;
}
if ($diff) {
    var_dump($diff);
    throw new AssertionError("Leftover ops!");
}

var_dump($builtPre);
//var_dump($locations);
var_dump("OK!");
