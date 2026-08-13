<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class Ranking {

    /**
     * @return list<int>|null
     */
    private static function distribute(?int $low, ?int $high, int $size): ?array {
        if ($low === null) {
            return $high === null || $high - $size < 1 ? null : range($high - $size, $high - 1);
        }

        if ($high === null) {
            return range($low + 1, $low + $size);
        }

        return $high - $low <= $size ? null : range($low + 1, $low + $size);
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private static function longestIncreasingSubsequence(array $values): array {
        $previous = array_fill(0, count($values), -1);
        $tails = [];

        foreach ($values as $index => $value) {
            $low = 0;
            $high = count($tails);

            while ($low < $high) {
                $mid = intdiv($low + $high, 2);

                if ($values[$tails[$mid]] < $value) {
                    $low = $mid + 1;
                } else {
                    $high = $mid;
                }
            }

            if ($low > 0) {
                $previous[$index] = $tails[$low - 1];
            }

            if ($low === count($tails)) {
                $tails[] = $index;
            } else {
                $tails[$low] = $index;
            }
        }

        $sequence = [];
        $index = $tails === [] ? -1 : $tails[count($tails) - 1];

        while ($index !== -1) {
            $sequence[] = $index;
            $index = $previous[$index];
        }

        return array_reverse($sequence);
    }

    /**
     * @param list<int> $rankings
     * @return list<int>
     */
    public static function reassign(array $rankings): array {
        $anchors = self::longestIncreasingSubsequence($rankings);
        $count = count($rankings);
        $new = $rankings;
        $previous = -1;

        foreach ([...$anchors, $count] as $boundary) {
            $size = $boundary - $previous - 1;

            if ($size > 0) {
                $low = $previous >= 0 ? $rankings[$previous] : null;
                $high = $boundary < $count ? $rankings[$boundary] : null;
                $values = self::distribute($low, $high, $size);

                if ($values === null) {
                    return array_map(fn (int $index): int => ($index + 1) * 100, array_keys($rankings));
                }

                foreach ($values as $offset => $value) {
                    $new[$previous + 1 + $offset] = $value;
                }
            }

            $previous = $boundary;
        }

        return array_values($new);
    }

}
