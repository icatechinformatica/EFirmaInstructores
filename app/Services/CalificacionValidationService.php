<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CalificacionValidationService
{
    /**
     * Valida que los alumnos con calificación aprobatoria tengan asistencia >= 80%
     * 
     * @param string $clave Clave del curso
     * @return array ['valido' => bool, 'errores' => array]
     */
    public function validarCalificacionesParaEnvio($clave)
    {
        $errores = [];

        // Obtener alumnos del curso mediante inner join
        $alumnos = DB::connection('pgsql')
            ->table('tbl_inscripcion as i')
            ->select(
                'i.id',
                'i.alumno',
                'i.calificacion',
                'i.porcentaje_asis'
            )
            ->join('tbl_cursos as c', function ($join) {
                $join->on('c.folio_grupo', '=', 'i.folio_grupo');
            })
            ->where('c.clave', $clave)
            ->where('i.status', 'INSCRITO')
            ->orderBy('i.alumno')
            ->get();

        // Validar cada alumno
        foreach ($alumnos as $alumno) {
            // Verificar si la calificación es aprobatoria (>= 7)
            if (is_numeric($alumno->calificacion) && $alumno->calificacion > 5) {
                // Verificar si la asistencia es menor a 80%
                $asistencia = $alumno->porcentaje_asis ?? 0;

                if ($asistencia < 80) {
                    $errores[] = "El alumno {$alumno->alumno} tiene asistencia de {$asistencia}%, para aprobar debe tener al menos el 80% de asistencia.";
                }
            }
        }

        return [
            'valido' => count($errores) === 0,
            'errores' => $errores,
            'total_alumnos' => count($alumnos),
            'alumnos_con_error' => count($errores)
        ];
    }

    /**
     * Valida que todos los alumnos tengan asistencias registradas y que
     * los alumnos con calificación aprobatoria tengan asistencia >= 80%
     * 
     * @param string $clave Clave del curso
     * @return array ['valido' => bool, 'errores' => array]
     */
    public function validarAsistenciasParaEnvio($clave)
    {
        $errores = [];

        // Obtener el ID del curso
        $idCurso = DB::connection('pgsql')
            ->table('tbl_cursos')
            ->where('clave', $clave)
            ->value('id');

        if (!$idCurso) {
            return [
                'valido' => false,
                'errores' => ['No se encontró el curso con la clave proporcionada.'],
                'total_alumnos' => 0,
                'alumnos_con_error' => 1
            ];
        }

        // Obtener lista de alumnos con sus asistencias y calificaciones
        $listaAlumnos = DB::connection('pgsql')
            ->table('tbl_inscripcion')
            ->select('asistencias', 'porcentaje_asis', 'calificacion', 'alumno', 'id_curso')
            ->where('id_curso', $idCurso)
            ->where('status', 'INSCRITO')
            ->get();

        // Validar cada alumno
        foreach ($listaAlumnos as $alumno) {
            // Validación 1: Verificar que tenga asistencias registradas
            if (is_null($alumno->asistencias)) {
                $errores[] = "El alumno {$alumno->alumno} no tiene asistencias/inasistencias registradas.";
                continue; // Pasar al siguiente alumno
            }

            // Validación 2: Si tiene calificación aprobatoria, verificar asistencia >= 80%
            $calif = intval($alumno->calificacion);
            if (is_numeric($alumno->calificacion) && $calif > 5) {
                if ($alumno->porcentaje_asis < 80) {
                    $errores[] = "El alumno {$alumno->alumno} tiene calificación aprobatoria ({$alumno->calificacion}) pero asistencia insuficiente ({$alumno->porcentaje_asis}%). Se requiere al menos 80% de asistencia.";
                }
            }
        }

        return [
            'valido' => count($errores) === 0,
            'errores' => $errores,
            'total_alumnos' => count($listaAlumnos),
            'alumnos_con_error' => count($errores)
        ];
    }
}
