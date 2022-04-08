<?php

ini_set('memory_limit', '640000M');

$wSolver = new WordleSolver("todas_tildes.txt", 6, true);
$wSolver->solve();

class WordleSolver
{
    private $dict;
    private $wordLength;
    private $accentMarks;
    private $replacer;
    private $wordReader;
    private $wordFilter;
    private $wordAnalizer;
    private $printer;

    function __construct($dict, $wordLength, $accentMarks)
    {
        $this->dict = $dict;
        $this->wordLength = $wordLength;
        $this->accentMarks = $accentMarks;
        $this->replacer = new WordReplacer($accentMarks ? WordReplacer::ACCENT_MARKS : WordReplacer::NO_ACCENT_MARKS);
        $this->wordReader = new WordReader($this->dict, $this->replacer);
        $this->wordFilter = new WordFilter();
        $this->wordAnalizer = new WordAnalizer();
        $this->printer = new Printer();
    }

    function solve() {
        $words = $this->wordReader->getAllWords();
        $words = $this->wordFilter->filter($words, $this->getRegex());
        $words = $this->wordAnalizer->sortWords($words);
        $words = $this->replacer->humanize($words);
        print($this->printer->toHtml($words));
    }

    function getRegex() { // TODO:
        return [
            // word length
            "/^.{".$this->wordLength."}$/",
            // green chars and yellow
            "/[^ca][^ia].i[^4]./",
            // yellow chars
            "/.*a.*/",
            "/.*i.*/",
            "/.*c.*/",
            "/.*4.*/",
            // gray chars
            "/[^1]{".$this->wordLength."}/",
            "/[^r]{".$this->wordLength."}/",
            "/[^e]{".$this->wordLength."}/",
            "/[^s]{".$this->wordLength."}/",
            "/[^m]{".$this->wordLength."}/",
            "/[^n]{".$this->wordLength."}/",
            // accent marks
            "/.*[12345].*/"
        ];
    }
}

/**
 * Replaces complex characters with numbers to prevent regex errors
 */
class WordReplacer
{
    const ACCENT_MARKS = 0;
    const NO_ACCENT_MARKS = 1;

    const ALL_REPLACES = [
        self::ACCENT_MARKS => [
            "ñ" => "0", "á" => "1", "é" => "2", "í" => "3",
            "ó" => "4", "ú" => "5", "ü" => "u", " " => "",
            "-" => ""
        ],
        self::NO_ACCENT_MARKS => [
            "ñ" => "0", "á" => "a", "é" => "e", "í" => "i",
            "ó" => "o", "ú" => "u", "ü" => "u", " " => "",
            "-" => ""
        ]
    ];

    const HUMANIZE_REPLACES = [
        "0" => "ñ", "1" => "á", "2" => "é", "3" => "í",
        "4" => "ó", "5" => "ú"
    ];

    private $replaces;

    function __construct($mode)
    {
        $this->replaces = self::ALL_REPLACES[$mode];
    }

    public function replace($word) {
        $word = strtolower($word);
        foreach ($this->replaces as $oldChar => $newChar) {
            $word = str_replace($oldChar, $newChar, $word);
        }
        return $word;
    }

    public function humanize($words) {
        foreach ($words as $key => $word) {
            foreach (self::HUMANIZE_REPLACES as $oldChar => $newChar) {
                $words[$key] = str_replace($oldChar, $newChar, $words[$key]);
            }
        }
        return $words;
    }
}

/**
 * Reads a dictionary a creates an array that contains all valid words
 */
class WordReader
{
    private $dict;
    private $replacer;

    function __construct($dict, $replacer)
    {
        $this->dict = $dict;
        $this->replacer = $replacer;
    }

    public function getAllWords() {
        $allWords = [];
        // obtener todas las palabras
        $handle = fopen($this->dict, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $word = trim($line);
                $word = $this->replacer->replace($word);
                if(true || $this->hasOnlyAllowedChars($word)) {
                    $allWords[] = $word;
                }
            }
            fclose($handle);
        } else {
            // error opening the file.
        } 
        return $allWords;
    }

    public function hasOnlyAllowedChars($word) {
        preg_match('/[^a-z0-9]/', $word, $matches);
        return empty($matches);
    }
}

/**
 * Sorts a list of words
 */
class WordAnalizer 
{
    private $charsOccurrences;

    // counts how many times each character appears in the dictionary
    private function countChars($words) {
        $chars = [];
        foreach ($words as $key => $word) {
            foreach (str_split($word) as $key => $char) {
                if(!isset($chars[$char])) {
                    $chars[$char] = 0;
                }
                $chars[$char] = $chars[$char] + 1;
            }
        }
        
        return $chars;
    }

    // assigns a score to each word based on how common its characters are, then sorts words
    public function sortWords($words) {
        $this->charsOccurrences = $this->countChars($words);
        $bestWords = [];
        foreach ($words as $key => $word) {
            $usedChars = [];
            $wordScore = 0;
            foreach (str_split($word) as $key => $char) {
                if(!in_array($char, $usedChars)) {
                    $usedChars[] = $char;
                    $wordScore += $this->charsOccurrences[$char];
                }
            }
            $bestWords[$word] = $wordScore;
        }
        asort($bestWords);
        $sortedWords = array_keys(array_reverse($bestWords));
        return $sortedWords;
    }
}

/**
 * Filters the words according to the patterns
 */
class WordFilter 
{
    public function filter($words, $regex) {
        $filteredWords = $words;
        foreach ($regex as $key => $reg) {
            $filteredWords = preg_grep($reg, $filteredWords);
        }
        return $filteredWords;
    }
}

class Printer
{
    public function toHtml($words) {
        $html = '
            <!DOCTYPE html>
            <html>
                <head>
                <meta charset="UTF-8">
                <title>Solver</title>
                </head>
                
                <body>
                    <ol>';
        foreach ($words as $key => $word) {
            $html .= ($key+1) . " " . mb_strtoupper($word) . "<br>";
        }
        $html .= '
                    </ol>
                </body>
            
            </html>
        ';
        return $html;
    }
}
?>