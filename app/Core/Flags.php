<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Maps team names (in Spanish) to country codes for flag display.
 * Uses flagcdn.com for SVG flags.
 */
final class Flags
{
    /** @var array<string, string> */
    private const CODES = [
        'Alemania'              => 'de',
        'Argentina'             => 'ar',
        'Brasil'                => 'br',
        'Francia'               => 'fr',
        'España'                => 'es',
        'Inglaterra'            => 'gb-eng',
        'Países Bajos'          => 'nl',
        'Portugal'              => 'pt',
        'Bélgica'               => 'be',
        'Colombia'              => 'co',
        'Croacia'               => 'hr',
        'Marruecos'             => 'ma',
        'Rep. de Corea'         => 'kr',
        'Senegal'               => 'sn',
        'Suiza'                 => 'ch',
        'Uruguay'               => 'uy',
        'Ecuador'               => 'ec',
        'EEUU'                  => 'us',
        'Japón'                 => 'jp',
        'México'                => 'mx',
        'Noruega'               => 'no',
        'Paraguay'              => 'py',
        'Suecia'                => 'se',
        'Turquía'               => 'tr',
        'Australia'             => 'au',
        'Austria'               => 'at',
        'Canadá'                => 'ca',
        'Chequia'               => 'cz',
        'Egipto'                => 'eg',
        'Escocia'               => 'gb-sct',
        'Irán'                  => 'ir',
        'Túnez'                 => 'tn',
        'Argelia'               => 'dz',
        'Catar'                 => 'qa',
        'Costa de Marfil'       => 'ci',
        'Ghana'                 => 'gh',
        'Panamá'                => 'pa',
        'RD Congo'              => 'cd',
        'Sudáfrica'             => 'za',
        'Uzbekistán'            => 'uz',
        'Arabia Saudí'          => 'sa',
        'Bosnia y Herzegovina'  => 'ba',
        'Curazao'               => 'cw',
        'Haití'                 => 'ht',
        'Irak'                  => 'iq',
        'Islas del Cabo Verde'  => 'cv',
        'Jordania'              => 'jo',
        'Nueva Zelanda'         => 'nz',
    ];

    public static function code(string $teamName): string
    {
        return self::CODES[$teamName] ?? '';
    }

    public static function img(string $teamName, int $width = 24): string
    {
        $code = self::code($teamName);
        if ($code === '') {
            return '';
        }
        $alt = htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8');
        $height = (int)round($width * 2 / 3);
        return sprintf(
            '<img src="/assets/flags/%s.svg" width="%d" height="%d" alt="%s" class="flag" loading="lazy">',
            $code,
            $width,
            $height,
            $alt
        );
    }
}
