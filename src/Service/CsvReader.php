<?php

namespace App\Service;

/**
 * Lit un fichier CSV d'import : UTF-8, séparateur « ; », la première ligne donne les noms des colonnes.
 * Au moindre doute le fichier est refusé, avant que la moindre ligne soit enregistrée.
 */
final class CsvReader
{
    /**
     * @param list<string> $columns les colonnes dont l'import a besoin, les autres sont libres
     *
     * @return list<array<string, string>> une ligne par enregistrement, indexée par nom de colonne
     */
    public function read(string $path, array $columns): array
    {
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(';', '"', '');

        $header = null;
        $rows = [];

        foreach ($file as $index => $fields) {
            // Excel en « CSV (séparateur point-virgule) » écrit les accents dans un autre encodage
            if (!mb_check_encoding(implode($fields), 'UTF-8')) {
                throw new \RuntimeException(sprintf('%s, ligne %d : caractères illisibles, enregistrez le fichier en « CSV UTF-8 ».', basename($path), $index + 1));
            }

            if ($header === null) {
                // Excel ajoute une marque invisible (BOM) devant le premier nom de colonne en « CSV UTF-8 »
                $header = [preg_replace('/^\xEF\xBB\xBF/', '', $fields[0]), ...array_slice($fields, 1)];

                if ($missing = array_diff($columns, $header)) {
                    throw new \RuntimeException(sprintf('%s : colonnes manquantes (%s).', basename($path), implode(', ', $missing)));
                }

                continue;
            }

            if (count($fields) !== count($header)) {
                throw new \RuntimeException(sprintf('%s, ligne %d : %d champs au lieu de %d.', basename($path), $index + 1, count($fields), count($header)));
            }

            $rows[] = array_combine($header, $fields);
        }

        // Sans en-tête, les colonnes n'ont jamais été contrôlées
        return $header !== null ? $rows : throw new \RuntimeException(basename($path) . ' : fichier vide.');
    }
}
