<?php declare(strict_types=1);

use danog\MadelineProto\FileRefExtractor\BuildMode\Ast;
use danog\MadelineProto\FileRefExtractor\Ops\ArrayOp;
use danog\MadelineProto\FileRefExtractor\Ops\CallOp;
use danog\MadelineProto\FileRefExtractor\Ops\ConstructorOp;
use danog\MadelineProto\FileRefExtractor\Ops\CopyMethodCallOp;
use danog\MadelineProto\FileRefExtractor\Ops\CopyOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetInputChannelOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetInputPeerOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetInputStickerSet;
use danog\MadelineProto\FileRefExtractor\Ops\GetInputUserOp;
use danog\MadelineProto\FileRefExtractor\Ops\GetMessageOp;
use danog\MadelineProto\FileRefExtractor\Ops\Noop;
use danog\MadelineProto\FileRefExtractor\Ops\PrimitiveLiteralOp;
use danog\MadelineProto\FileRefExtractor\Ops\ThemeFormatOp;
use danog\MadelineProto\FileRefExtractor\Path;
use danog\MadelineProto\FileRefExtractor\TLContext;
use danog\MadelineProto\FileRefExtractor\TLWrapper;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Magic;
use danog\MadelineProto\Settings\TLSchema;
use danog\MadelineProto\TL\TL;

require 'vendor/autoload.php';

$schema = new TLSchema;

if (isset($argv[1])) {
    $schema->setAPISchema($argv[1]);
}

Magic::start(false);

$TL = new TL(null);
$TL->init($schema);

$TL = new TLWrapper($TL);
$locations = [];
Logger::log("Generating file reference map...");

foreach ($TL->getConstructorsOfType('Message') as $constructor => $_) {
    if ($constructor === 'messageEmpty') {
        continue;
    }
    $locations[$constructor][] = new GetMessageOp(
        new GetInputPeerOp(new Path([[$constructor, 'peer_id']])),
        new CopyOp([[$constructor, 'id']]),
        $constructor === 'message' ? new CopyOp([[$constructor, 'from_scheduled', Path::FLAG_PASSTHROUGH]]) : null,
        'fileSourceMessage',
    );
}

$storyMethods = [];
//foreach (['stories.StoryViewsList', 'stories.Stories', 'stories.PeerStories', 'stories.StoryReactionsList'] as $t) {
foreach (['stories.Stories'] as $t) {
    foreach ($TL->getMethodsOfType($t) as $method => $_) {
        $storyMethods[$method] = true;
        $locations['storyItem'][] = new CallOp(
            'stories.getStoriesByID',
            [
                'id' => new ArrayOp(new CopyOp([['storyItem', 'id']])),
                'peer' => new GetInputPeerOp(new Path([[$method, 'peer']], true)),
            ],
            'fileSourceStory'
        );
    }
}
/*
foreach (['stories.Stories'] as $t) {
    foreach ($TL->getMethodsOfType($t) as $method => $_) {
        $storyMethods[$method] = true;
        $locations[$method][] = new CallOp(
            'stories.getStoriesByID',
            [
                'id' => new ArrayOp(new CopyOp([
                    [$method, ''],
                    ['stories.stories', 'stories', Path::FLAG_UNPACK_ARRAY],
                    ['storyItem', 'id'],
                ])),
                'peer' => new GetInputPeerOp(new Path([[$method, 'peer']])),
            ]
        );
    }
}*/

$locations['storyViewPublicRepost'][] = new CallOp(
    'stories.getStoriesByID',
    [
        'id' => new ArrayOp(new CopyOp([['storyViewPublicRepost', 'story'], ['storyItem', 'id']])),
        'peer' => new GetInputPeerOp(new Path([['storyViewPublicRepost', 'peer_id']])),
    ],
    'fileSourceStory'
);
$locations['storyReactionPublicRepost'][] = new CallOp(
    'stories.getStoriesByID',
    [
        'id' => new ArrayOp(new CopyOp([['storyReactionPublicRepost', 'story'], ['storyItem', 'id']])),
        'peer' => new GetInputPeerOp(new Path([['storyReactionPublicRepost', 'peer_id']])),
    ],
    'fileSourceStory'
);

/*$locations['peerStories'][] = new CallOp(
    'stories.getStoriesByID',
    [
        'id' => new ArrayOp(new CopyOp([['peerStories', 'stories', Path::FLAG_UNPACK_ARRAY], ['storyItem', 'id']])),
        'peer' => new GetInputPeerOp(new Path([['peerStories', 'peer']])),
    ]
);*/

$locations['storyItem'][] = new CallOp(
    'stories.getStoriesByID',
    [
        'id' => new ArrayOp(new CopyOp([['storyItem', 'id']])),
        'peer' => new GetInputPeerOp(new Path([['peerStories', 'peer']], true)),
    ],
    'fileSourceStory'
);

foreach (['foundStory', 'publicForwardStory', 'webPageAttributeStory', 'messageMediaStory'] as $c) {
    $optional = $c === 'webPageAttributeStory' || $c === 'messageMediaStory';
    $locations[$c][] = new CallOp(
        'stories.getStoriesByID',
        [
            'id' => new ArrayOp(new CopyOp([$optional ? [$c, 'story', Path::FLAG_IF_ABSENT_ABORT] : [$c, 'story'], ['storyItem', 'id']])),
            'peer' => new GetInputPeerOp(new Path([[$c, 'peer']])),
        ],
        'fileSourceStory'
    );
}

$locations['webPage'][] = CallOp::simple(
    'messages.getWebPage',
    'webPage',
    ['url' => 'url', 'hash' => new PrimitiveLiteralOp('int', 0)],
    'fileSourceWebPage'
);

$locations['botApp'][] = CallOp::simple('messages.getBotApp', 'botApp', [
    'app' => new ConstructorOp(
        'inputBotAppID',
        [
            'id' => new CopyOp([['botApp', 'id']]),
            'access_hash' => new CopyOp([['botApp', 'access_hash']]),
        ]
    ),
    'hash' => new PrimitiveLiteralOp('long', 0),
], 'fileSourceBotApp');

$locations['botInfo'][] = new CallOp(
    'users.getFullUser',
    ['id' => new GetInputUserOp(new Path([['botInfo', 'user_id', Path::FLAG_IF_ABSENT_ABORT]]))],
    'fileSourceUserFull'
);
$locations['storyItem'][] = new CallOp('stories.getStoriesByID', [
    'id' => new ArrayOp(new CopyOp([['storyItem', 'id']])),
    'peer' => new GetInputPeerOp(new Path([['storyItem', 'from_id', Path::FLAG_IF_ABSENT_ABORT]])),
], 'fileSourceStory');

//$locations['messages.getSponsoredMessages'][] = new CopyMethodCallOp('messages.getSponsoredMessages', 'fileSourceSponsoredMessage');
$locations['messages.getSponsoredMessages'][] = new Noop('Do not store file references from sponsored messages');

$locations['channelAdminLogEvent'][] = new CallOp(
    'channels.getAdminLog',
    [
        'channel' => new GetInputChannelOp(new Path([['channels.getAdminLog', 'channel']], true)),
        'max_id' => new CopyOp([['channelAdminLogEvent', 'id']]),
        'min_id' => new CopyOp([['channelAdminLogEvent', 'id']]),
        'limit' => new PrimitiveLiteralOp('int', 1),
        'q' => new PrimitiveLiteralOp('string', ''),
    ],
    'fileSourceAdminLog'
);

/*
$locations['channels.getAdminLog'][] = new CallOp(
    'channels.getAdminLog',
    [
        'channel' => new GetInputChannelOp(new Path([['channels.getAdminLog', 'channel']])),
        'max_id' => new CopyOp([
            ['channels.getAdminLog', ''],
            ['channels.adminLogResults', 'events', Path::FLAG_UNPACK_ARRAY],
            ['channelAdminLogEvent', 'id'],
        ]),
        'min_id' => new CopyOp([
            ['channels.getAdminLog', ''],
            ['channels.adminLogResults', 'events', Path::FLAG_UNPACK_ARRAY],
            ['channelAdminLogEvent', 'id'],
        ]),
        'limit' => new PrimitiveLiteralOp('int', 1),
        'q' => new PrimitiveLiteralOp('string', ''),
    ]
);*/

foreach (['stories.createAlbum', 'stories.getAlbums', 'stories.updateAlbum'] as $m) {
    $locations[$m][] = new CallOp('stories.getAlbums', [
        'peer' => new GetInputPeerOp(new Path([[$m, 'peer']])),
        'hash' => new PrimitiveLiteralOp('long', 0),
    ], 'fileSourceStoryAlbum');
}

$locations['bots.getPreviewMedias'][] = new CopyMethodCallOp('bots.getPreviewMedias', 'fileSourceBotPreviewMedia');
$locations['bots.getPreviewInfo'][] = new CopyMethodCallOp('bots.getPreviewInfo', 'fileSourceBotPreviewInfo');
$locations['bots.addPreviewMedia'][] = new CallOp('bots.getPreviewInfo', [
    'bot' => new GetInputUserOp(new Path([['bots.addPreviewMedia', 'bot']])),
    'lang_code' => new CopyOp([['bots.addPreviewMedia', 'lang_code']]),
], 'fileSourceBotPreviewInfo');
$locations['bots.editPreviewMedia'][] = new CallOp('bots.getPreviewInfo', [
    'bot' => new GetInputUserOp(new Path([['bots.editPreviewMedia', 'bot']])),
    'lang_code' => new CopyOp([['bots.editPreviewMedia', 'lang_code']]),
], 'fileSourceBotPreviewInfo');

$locations['updateMessageExtendedMedia'][] = new CallOp(
    'messages.getExtendedMedia',
    [
        'id' => new ArrayOp(new CopyOp([['updateMessageExtendedMedia', 'msg_id']])),
        'peer' => new GetInputPeerOp(new Path([['updateMessageExtendedMedia', 'peer']])),
    ],
    'fileSourcePaidMedia'
);
$locations['userFull'][] = new CallOp(
    'users.getFullUser',
    [
        'id' => new GetInputUserOp(new Path([['userFull', 'id']])),
    ],
    'fileSourceUserFull'
);
$locations['chatFull'][] = new CallOp(
    'messages.getFullChat',
    [
        'chat_id' => new CopyOp([['chatFull', 'id']]),
    ],
    'fileSourceChatFull'
);
$locations['channelFull'][] = new CallOp(
    'channels.getFullChannel',
    [
        'channel' => new GetInputChannelOp(new Path([['channelFull', 'id']])),
    ],
    'fileSourceChannelFull'
);
$locations['help.getPremiumPromo'][] = new CopyMethodCallOp('help.getPremiumPromo', 'fileSourcePremiumPromo');

$starMethods = [];
foreach ($TL->getMethodsOfType('payments.StarsStatus') as $method => $_) {
    $starMethods[$method] = true;
    $locations['starsTransaction'][] = new CallOp(
        'payments.getStarsTransactionsByID',
        [
            'peer' => new GetInputPeerOp(new Path([[$method, 'peer']], true)),
            ...($method === 'payments.getStarsSubscriptions' ? [] : ['ton' => new CopyOp(new Path([[$method, 'ton', Path::FLAG_PASSTHROUGH]], true))]),
            'id' => new ArrayOp(new ConstructorOp(
                'inputStarsTransaction',
                [
                    'id' => new CopyOp([['starsTransaction', 'id']]),
                    'refund' => new CopyOp([['starsTransaction', 'refund', Path::FLAG_PASSTHROUGH]]),
                ]
            )),
        ],
        'fileSourceStarsTransaction'
    );/*
    $locations[$method][] = new CallOp(
        'payments.getStarsTransactionsByID',
        [
            'peer' => new CopyOp([[$method, 'peer']]),
            ...($method === 'payments.getStarsSubscriptions' ? [] : ['ton' => new CopyOp([[$method, 'ton', Path::FLAG_PASSTHROUGH]])]),
            'id' => new ArrayOp(new ConstructorOp(
                'inputStarsTransaction',
                [
                    'id' => new CopyOp([
                        [$method, ''],
                        ['payments.starsStatus', 'history', Path::FLAG_IF_ABSENT_ABORT|Path::FLAG_UNPACK_ARRAY],
                        ['starsTransaction', 'id'],
                    ]),
                    'refund' => new CopyOp([
                        [$method, ''],
                        ['payments.starsStatus', 'history', Path::FLAG_IF_ABSENT_ABORT|Path::FLAG_UNPACK_ARRAY],
                        ['starsTransaction', 'refund', Path::FLAG_PASSTHROUGH],
                    ]),
                ]
            )),
        ]
    );*/
}
$locations['attachMenuBot'][] = new CallOp(
    'messages.getAttachMenuBot',
    ['bot' => new GetInputUserOp(new Path([['attachMenuBot', 'bot_id']]))],
    'fileSourceAttachMenuBot'
);
$locations['theme'][] = new CallOp(
    'account.getTheme',
    [
        'theme' => new ConstructorOp(
            'inputTheme',
            [
                'id' => new CopyOp([['theme', 'id']]),
                'access_hash' => new CopyOp([['theme', 'access_hash']]),
            ]
        ),
        'format' => new ThemeFormatOp(),
    ],
    'fileSourceTheme'
);
$locations['wallPaper'][] = new CallOp(
    'account.getWallPaper',
    [
        'wallpaper' => new ConstructorOp(
            'inputWallPaper',
            [
                'id' => new CopyOp([['wallPaper', 'id']]),
                'access_hash' => new CopyOp([['wallPaper', 'access_hash']]),
            ]
        ),
    ],
    'fileSourceWallPaper'
);

// Multiple variations to handle references from covers in StickerSetCovered and messages.StickerSet
foreach (['stickerSetMultiCovered', 'stickerSetFullCovered'] as $c) {
    $locations[$c][] = new CallOp(
        'messages.getStickerSet',
        [
            'stickerset' => new GetInputStickerSet(new Path([[$c, 'set']])),
            'hash' => new PrimitiveLiteralOp('int', 0),
        ],
        'fileSourceStickerSet'
    );
}
$locations['messages.stickerSet'][] = new CallOp(
    'messages.getStickerSet',
    [
        'stickerset' => new GetInputStickerSet(new Path([['messages.stickerSet', 'set']])),
        'hash' => new PrimitiveLiteralOp('int', 0),
    ],
    'fileSourceStickerSet'
);
$locations['messages.savedGifs'][] = new CallOp('messages.getSavedGifs', ['hash' => new PrimitiveLiteralOp('long', 0)], 'fileSourceSavedGifs');
foreach (['account.savedRingtones', 'account.savedRingtoneConverted', 'account.uploadRingtone'] as $c) {
    $locations[$c][] = new CallOp('account.getSavedRingtones', ['hash' => new PrimitiveLiteralOp('long', 0)], 'fileSourceSavedRingtones');
}

$locations['recentMeUrlChatInvite'][] = new Noop('Do not store references based on chat invite links');
$locations['messages.checkChatInvite'][] = new Noop('Do not store references based on chat invite links');

$locations['messages.availableEffects'][] = new CallOp(
    'messages.getAvailableEffects',
    ['hash' => new PrimitiveLiteralOp('int', 0)],
    'fileSourceAvailableEffects'
);
$locations['messages.availableReactions'][] = new CallOp(
    'messages.getAvailableReactions',
    ['hash' => new PrimitiveLiteralOp('int', 0)],
    'fileSourceAvailableReactions'
);

$locations['photo'][] = new CallOp(
    'photos.getUserPhotos',
    [
        'user_id' => new GetInputUserOp(new Path([['photos.getUserPhotos', 'user_id']], true)),
        'offset' => new PrimitiveLiteralOp('int', -1),
        'max_id' => new CopyOp([['photo', 'id']]),
        'limit' => new PrimitiveLiteralOp('int', 1),
    ],
    'fileSourceUserProfilePhoto'
);
/*
$locations['photos.getUserPhotos'][] = new CallOp(
    'photos.getUserPhotos',
    [
        'user_id' => new CopyOp([['photos.getUserPhotos', 'user_id']]),
        'offset' => new PrimitiveLiteralOp('int', -1),
        'max_id' => new CopyOp([
            ['photos.getUserPhotos', ''],
            ['photos.photos', 'photos', Path::FLAG_UNPACK_ARRAY],
            ['photo', 'id'],
        ]),
        'limit' => new PrimitiveLiteralOp('int', 1),
    ]
);*/

foreach (['photos.updateProfilePhoto', 'photos.uploadProfilePhoto'] as $method) {
    $locations[$method][] = new CallOp(
        'photos.getUserPhotos',
        [
            'user_id' => new GetInputUserOp(new Path(
                [[
                    $method,
                    'bot',
                    new ConstructorOp(
                        'inputUserSelf',
                        []
                    ),
                ]]
            )),
            'offset' => new PrimitiveLiteralOp('int', -1),
            'max_id' => new CopyOp([[$method, ''], ['photos.photo', 'photo'], ['photo', 'id']]),
            'limit' => new PrimitiveLiteralOp('int', 1),
        ],
        'fileSourceUserProfilePhoto'
    );
}
$locations['photos.uploadContactProfilePhoto'][] = new CallOp(
    'photos.getUserPhotos',
    [
        'user_id' => new GetInputUserOp(
            new Path([['photos.uploadContactProfilePhoto', 'user_id']]),
        ),
        'offset' => new PrimitiveLiteralOp('int', -1),
        'max_id' => new CopyOp([['photos.uploadContactProfilePhoto', ''], ['photos.photo', 'photo'], ['photo', 'id']]),
        'limit' => new PrimitiveLiteralOp('int', 1),
    ],
    'fileSourceUserProfilePhoto'
);
$locations['messages.getInlineBotResults'][]= new Noop('Inline bot results are ephemeral');
$locations['messages.getPreparedInlineMessage'][]= new Noop('Inline bot results are ephemeral');

$locations['messages.uploadMedia'][]= new Noop('A freshly uploaded media file will obtain a context only once it is sent to a chat');
$locations['messages.uploadImportedMedia'][]= new Noop('A freshly uploaded media file will obtain a context only once it is sent to a chat');

$locations['document'][] = new CallOp(
    'messages.getStickerSet',
    [
        'stickerset' => new GetInputStickerSet(new Path([['document', 'attributes']])),
        'hash' => new PrimitiveLiteralOp('int', 0),
    ],
    'fileSourceStickerSet'
);
$locations['messages.getDocumentByHash'][] = new CopyMethodCallOp('messages.getDocumentByHash', 'fileSourceDocumentByHash');
$locations['updateServiceNotification'][] = new Noop('Cannot refetch service notifications');

$locations['messages.getWebPagePreview'][] = new Noop("No locations are added for the method call, as it doesn't use persistent IDs as input; the location is instead extracted from the persistent IDs in the returned WebPage object");

// Ignore these for now
foreach (['payments.ResaleStarGifts', 'payments.StarGiftUpgradePreview', 'StarGift'] as $type) {
    foreach ($TL->getConstructorsOfType($type) as $constructor => $_) {
        $locations[$constructor][] = new Noop('Contexts for star gifts are not yet implemented');
    }
}

$recurse = static function (Closure $onStackEnd, string $type, array &$stack, array &$stackTypes) use ($TL, &$recurse): void {
    if ($type === 'Update' || $type === 'Updates') {
        $onStackEnd($stack);
        return;
    }
    if ($type === 'PeerStories') {
        $onStackEnd($stack);
    }

    $pos = count($stack);
    foreach ([...$TL->tl->getConstructors()->by_id, ...$TL->tl->getMethods()->by_id] as $constructor) {
        $predicate = $constructor['predicate'] ?? $constructor['method'];
        if ($predicate === 'updateShortMessage' || $predicate === 'updateShortChatMessage' || $predicate === 'updateShortSentMessage') {
            // Assume these are converted to message constructors by the client.
            continue;
        }
        $t = $constructor['type'];
        $stackTypes[$t] ??= 0;
        if ($stackTypes[$t] > 1) {
            continue;
        }
        $stackTypes[$t]++;
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
                    $stack[$pos][2] = Path::FLAG_IF_ABSENT_ABORT;
                }
                if (isset($param['subtype'])) {
                    $oldFlag = $stack[$pos][2] ?? 0;
                    $stack[$pos][2] = $oldFlag | Path::FLAG_UNPACK_ARRAY;
                }
                $recurse($onStackEnd, $t, $stack, $stackTypes);
                unset($stack[$pos]);

            }
        }
        $stackTypes[$t]--;
    }
    foreach ($TL->getMethodsOfType($type, true) as $method => $data) {
        $stack[$pos] = [$method, ''];
        $onStackEnd($stack);
    }
    foreach ($TL->getMethodsOfType("Vector<$type>", true) as $method => $data) {
        $stack[$pos] = [$method, '', Path::FLAG_UNPACK_ARRAY];
        $onStackEnd($stack);
    }
    unset($stack[$pos]);
};

$pre = [
    'fileSourceMessage' => [
        'flags' => '#',
        'from_scheduled' => 'flags.0?true',
        'peer' => 'long',
        'id' => 'int',
    ],
    'fileSourceStarsTransaction' => [
        'flags' => '#',
        'peer' => 'long',
        'id' => 'string',
        'refund' => 'flags.0?true',
        'ton' => 'flags.1?true',
    ],
];

$validated = [];

$tmp = new Ast(allowUnpacking: true, outputSchema: $pre);
foreach (['Document' => 'document', 'Photo' => 'photo'] as $type => $constructor) {
    $stack = [[$constructor, 'file_reference']];
    $stackTypes = [$type => 1];
    $recurse(
        static function (array $stack) use ($locations, $TL, $tmp, &$validated, $storyMethods, $starMethods): void {
            $slice = [];
            $hadAny = false;
            $skippedDueToFlags = [];
            $top = end($stack)[0];
            for ($x = count($stack)-1; $x >= 0; $x--) {
                $pair = $stack[$x];
                foreach ($locations[$pair[0]] ?? [] as $op) {
                    $normalized = $op->normalize($slice, $pair[0], false);
                    if ($normalized === null) {
                        continue;
                    }
                    $hadAny = true;
                    $normalized->build(new TLContext($TL, $tmp, $top, $TL->isConstructor($top)));
                    $validated[$pair[0]][spl_object_id($op)] = $op;

                    $normalized = $op->normalize($slice, $pair[0], true);
                    if ($normalized === null) {
                        $skippedDueToFlags []= $op;
                        continue;
                    }
                }
                $slice[] = $pair;
            }
            if (!$hadAny) {
                throw new AssertionError("Uncovered path: " . json_encode($stack));
            }
            if ($skippedDueToFlags) {
                if ($top === 'updateStory'
                    || $top === 'peerStories'
                    // The two above always have the story peer flag set.

                    || isset($storyMethods[$top])
                    || isset($starMethods[$top])

                    // The two above always have the story peer/all star flags set.

                    || $top === 'messages.getFullChat'
                    || $top === 'channels.getFullChannel'
                    || $top === 'users.getFullUser'
                    // The three above are related to botInfo, ignore as we already store a context for the chat info.
                ) {
                    return;
                }
                foreach ($slice as [$cons]) {
                    if ($cons === 'webPageAttributeStory'
                        || $cons === 'messageMediaStory'
                        || $cons === 'foundStory'
                        || $cons === 'publicForwardStory'
                        || $cons === 'peerStories'
                        || $cons === 'storyViewPublicRepost'
                        || $cons === 'storyReactionPublicRepost'
                    ) {
                        // The above always have all necessary flags set
                        return;
                    }
                }
                var_dump($skippedDueToFlags);
                throw new AssertionError("Uncovered path (didn't have at least one unflagged context): " . json_encode($stack));
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

$output = new Ast(allowUnpacking: false, outputSchema: $pre);
foreach ($locations as $constructor => $ops) {
    foreach ($ops as $idx => $op) {
        $op->build(new TLContext($TL, $output, $constructor, $TL->isConstructor($constructor)));
    }
}

$output->write(
    __DIR__.'/../src/file_ref_map.dat',
    __DIR__.'/../src/TL_filerefs_db.tl',
);

echo("OK!\n".PHP_EOL);
