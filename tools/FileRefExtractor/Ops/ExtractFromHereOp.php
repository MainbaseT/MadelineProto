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

use danog\MadelineProto\FileRefExtractor\FieldExtractorOp;
use danog\MadelineProto\FileRefExtractor\TLContext;
use danog\MadelineProto\FileRefExtractor\TypedOp;
use Webmozart\Assert\Assert;

final readonly class ExtractFromHereOp implements FieldExtractorOp
{
    public function __construct(
        /** @var string[] */
        public readonly array $path,
        public readonly bool $isFlag = false,
        public readonly ?TypedOp $ifEmptyFlag = null,
    ) {
        if ($ifEmptyFlag !== null) {
            Assert::true($isFlag);
        }
    }

    public function normalize(array $stack, string $current): ?\danog\MadelineProto\FileRefExtractor\BaseOp
    {
        $if = $this->ifEmptyFlag?->normalize($stack, $current);
        if ($if === null && $this->ifEmptyFlag !== null) {
            return null;
        }
        Assert::eq($current, $this->path[0]);
        return new self(
            [...$stack, ...$this->path],
            $this->isFlag,
            $if
        );
    }

    public function getType(TLContext $tl): string
    {
        $t = $tl->getTypeAtPosition($this);
        if ($this->ifEmptyFlag !== null) {
            Assert::eq($this->ifEmptyFlag->getType($tl), $t);
        }
        return $t;
    }

    public function extend(string ...$path): self
    {
        return new self(...$this->path, ...$path);
    }

    public function build(TLContext $tl): array
    {
        // Validate
        $this->getType($tl);
        return [
            'op' => 'extractFromHere',
            'isFlag' => $this->isFlag,
            'ifFlagEmptyUse' => $this->ifEmptyFlag?->build($tl),
            'path' => $this->path,
        ];
    }
}
