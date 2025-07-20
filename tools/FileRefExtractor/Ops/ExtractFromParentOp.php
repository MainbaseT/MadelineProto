<?php

declare(strict_types=1);

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

namespace danog\MadelineProto\FileRefExtractor\Ops;

use danog\MadelineProto\FileRefExtractor\BuildMode\Ast;
use danog\MadelineProto\FileRefExtractor\BuildMode\Flat;
use danog\MadelineProto\FileRefExtractor\FieldExtractorOp;
use danog\MadelineProto\FileRefExtractor\TLContext;
use Webmozart\Assert\Assert;

final readonly class ExtractFromParentOp extends FieldExtractorOp
{
    public function normalize(array $stack, string $current, bool $ignoreFlag): ?\danog\MadelineProto\FileRefExtractor\TypedOp
    {
        if ($stack[0][0] !== $this->path[0][0]) {
            return null;
        }
        $new = [];
        foreach ($this->path as $i => $part) {
            if ($ignoreFlag && \array_key_exists(2, $part) && $part[2] === null) {
                return null;
            }
            if (isset($part[2]) && $part[2] !== true) {
                $n = $part[2]->normalize($stack, $current, $ignoreFlag);
                if ($n === null) {
                    return null;
                }
                $part[2] = $n;
            }
            $new[$i] = $part;
        }
        return new ExtractFromHereOp($new);
    }

    public function build(TLContext $tl): array
    {
        // Validate
        $t = $this->getType($tl);
        if ($tl->buildMode instanceof Flat) {
        } elseif ($tl->buildMode instanceof Ast) {
            /*Assert::eq($tl->buildMode->needsMethod ?? $this->path[0][0], $this->path[0][0]);
            $tl->buildMode->needsMethod = $this->path[0][0];*/
        }
        $new = [];
        foreach ($this->path as $part) {
            if (isset($part[2]) && $part[2] !== true) {
                $part[2] = $part[2]->build($tl);
            }
            $new[] = $part;
        }
        return [
            'op' => 'extractFromMethodCall',
            'path' => $this->path,
        ];
    }
}
