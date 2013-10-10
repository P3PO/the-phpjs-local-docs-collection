<?php

/**
 * PHP Manual lookup file. Adapted from PHP.net's version.
 *
 * Checks if a file exists for given pattern in the url. If so,
 * redirects there. Else, redirects to home page.
 *
 * @return unknown
 */

// $Id: manual-lookup.inc,v 1.39 2009/04/10 09:50:49 bjori Exp $

// Get all manual prefix search sections
function get_manual_search_sections() {
    return array(
        "", "book.", "ref.", "function.", "class.",
        "feature-", "control-structures.", "language.",
        "about.", "faq.", "features.",
    );
}

// Try to find some variations of keyword with $prefix in the $lang manual
function tryprefix($lang, $keyword, $prefix)
{
    // Replace all underscores with hyphens (phpdoc convention)
    $keyword = str_replace("_", "-", $keyword);

    // Replace everything in parentheses with a hyphen (ie. function call)
    $keyword = preg_replace("!\\(.*\\)!", "-", $keyword);


    // Try the keyword with the prefix
    $try = "/docs/php/br/${prefix}${keyword}.html";

    if (@file_exists($_SERVER['DOCUMENT_ROOT'] . $try)) { return $try; }

    // Drop out spaces, and try that keyword (if different)
    $nosp = str_replace(" ", "", $keyword);
    if ($nosp != $keyword) {
        $try = "/docs/php/br/${prefix}${nosp}.html";
        if (@file_exists($_SERVER['DOCUMENT_ROOT'] . $try)) { return $try; }
    }

    // Replace spaces with hyphens, and try that (if different)
    $dasp = str_replace(" ", "-", $keyword);
    if ($dasp != $keyword) {
        $try = "/docs/php/br/${prefix}${dasp}.html";
        if (@file_exists($_SERVER['DOCUMENT_ROOT'] . $try)) { return $try; }
    }

    // Remove hyphens (and underscores), and try that (if different)
    $noul = str_replace("-", "", $keyword);
    if ($noul != $keyword) {
        $try = "/docs/php/br/${prefix}${noul}.html";
        if (@file_exists($_SERVER['DOCUMENT_ROOT'] . $try)) { return $try; }
    }

    // Nothing found
    return "";
}

// Try to find a manual page in a specified language
// for the specified "keyword". Using the sqlite is
// better because then stat() calls are eliminated.
function find_manual_page_slow($lang, $keyword)
{
    // Possible prefixes to test
    $sections = get_manual_search_sections();

    // Remove .. for security reasons
    $keyword = str_replace("..", "", $keyword);

    // Try to find a manual page with the specified prefix
    foreach ($sections as $section) {
        $found = tryprefix($lang, $keyword, $section);
        if ($found) { return $found; }
    }
exit;
    // Fall back to English, if the language was not English,
    // and nothing was found so far for any of the prefixes
    if ($lang != "en") {
        foreach ($sections as $section) {
            $found = tryprefix("en", $keyword, $section);
            if ($found) { return $found; }
        }
    }

    // BC: Few references pages where moved to book.
    if (strpos($keyword, "ref.") === 0) {
        $kw = substr_replace($keyword, "book.", 0, 4);
        return find_manual_page($lang, $kw);
    }

    // Nothing found
    return "";
}

// If sqlite is available on this mirror use that for manual
// page shortcuts, so we avoid stat() calls on the server
function find_manual_page($lang, $keyword)
{
    // If there is no sqlite support, or we are unable to
    // open the database, fall back to normal search. Use
    // open rather than popen to avoid any chance of confusion
    // when rsync updates the database
    if (!function_exists("sqlite_open") ||
        !($s = @sqlite_open($_SERVER['DOCUMENT_ROOT'] . '/backend/manual-lookup.sqlite'))) {
        return find_manual_page_slow($lang, $keyword);
    }
    $kw = $keyword;

    // Try the preferred language first, then the
    // English one in case no page is found
    $langs = ($lang != 'en') ?
        array(sqlite_escape_string($lang), 'en') :
        array('en');

    // Reformat keyword, drop anything in parenthesis
    $keyword = str_replace('_', '-', $keyword);
    if (strpos($keyword, '(') !== FALSE) {
        $keyword = preg_replace("!\\(.*\\)!", "-", $keyword);
    }

    // No keyword to search for
    if (strlen($keyword) == 0) {
        return "";
    }

    // NB: sqlite wants ' quoted as ''
    $keyword = sqlite_escape_string($keyword);

    // If there is a dot in the $keyword, then a prefix
    // is specfied, so we need to carry that on into the SQL
    // search [eg. function.echo or function.mysql-close]
    if (strpos($keyword, ".") > 0) {
        $SQL = "SELECT name from fs WHERE name LIKE '%$keyword.php'
                AND lang = 'LANG_HERE' ORDER BY prio LIMIT 1";

    // Some partially specified URL is used
    } else {

        // List a few variations, plus a metaphone version
        // FIXME: metaphone causes too many false positives, disable for now
        //        the similar_text() search fallback works fine (see quickref.php)
        //        if this change remains, adjust the gen-phpweb-sqlite script accordingly
        $keywords = array_unique(array(
            $keyword,
            str_replace(' ', '',  $keyword),
            str_replace(' ', '-', $keyword),
            str_replace('-', '',  $keyword),
    //        metaphone($keyword)
        ));

        // List of all the keywords in single quotes
        $words = "'" . implode("','", $keywords) . "'";

        $SQL = "SELECT name, prio FROM fs WHERE lang = 'LANG_HERE'
                AND keyword IN (" . $words . ") ORDER BY prio LIMIT 1";
    }

    // Check for all the languages
    foreach ($langs as $lang) {
        $q = @sqlite_query($s, str_replace('LANG_HERE', $lang, $SQL));

        // Successful query
        if ($q) {
            $r = sqlite_fetch_array($q, SQLITE_NUM);
            if (isset($r[0])) {
                if(isset($r[1]) && $r[1] > 10 && strlen($keyword) < 4) {
                    // "Match" found, but the keyword is so short
                    // its probably bogus. Skip it
                    continue;
                }
                // Match found
                // But does the file really exist?
                if (file_exists($_SERVER["DOCUMENT_ROOT"] . $r[0])) {
                    return $r[0];
                }
            }
        } else {
            error_noservice();
        }
    }

    // No match found
    return find_manual_page_slow($lang, $kw);
}

// Find the page from pattern and language
$page = find_manual_page($_GET['lang'], $_GET['pattern']);
if ($page != '')
{
	header("Location: $page");
}
else
{
	header("Location: index.html");
}
?>
