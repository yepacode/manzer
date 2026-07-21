<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrabajadorController;
use App\Http\Controllers\TrabajadorBonoController;
use App\Http\Controllers\TipoHoraController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\NominaImportacionController;
use App\Http\Controllers\CuadrillaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteEmailController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\ObraConceptoProduccionController;
use App\Http\Controllers\ObraDiscrepanciaController;
use App\Http\Controllers\FichajeController;
use App\Http\Controllers\ParteDiarioController;
use App\Http\Controllers\GastoCategoriaController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\MaquinariaController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\SubcontrataController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ContratoTipoController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\EpiCatalogoController;
use App\Http\Controllers\EpiInventarioController;
use App\Http\Controllers\DocumentoEmpresaController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\TableroController;
use App\Http\Controllers\TableroColumnaController;
use App\Http\Controllers\TableroEtiquetaController;
use App\Http\Controllers\TarjetaController;
use App\Http\Controllers\TarjetaChecklistController;
use App\Http\Controllers\TarjetaComentarioController;
use App\Http\Controllers\TarjetaAdjuntoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Dashboard principal
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// ==========================================
// RUTAS DE PERFIL (autenticadas)
// ==========================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
});

// ==========================================
// RUTAS DE BONOS DE TRABAJADORES (antes de rutas con parámetro {trabajador})
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad'])->group(function () {
    Route::get('trabajadores/bonos', [TrabajadorBonoController::class, 'index'])->name('trabajadores.bonos.index');
    Route::get('trabajadores/bonos/create', [TrabajadorBonoController::class, 'create'])->name('trabajadores.bonos.create');
    Route::post('trabajadores/bonos', [TrabajadorBonoController::class, 'store'])->name('trabajadores.bonos.store');
    Route::get('trabajadores/bonos/{bono}/edit', [TrabajadorBonoController::class, 'edit'])->name('trabajadores.bonos.edit');
    Route::put('trabajadores/bonos/{bono}', [TrabajadorBonoController::class, 'update'])->name('trabajadores.bonos.update');
    Route::delete('trabajadores/bonos/{bono}', [TrabajadorBonoController::class, 'destroy'])->name('trabajadores.bonos.destroy');
    Route::post('trabajadores/bonos/{bono}/pagar', [TrabajadorBonoController::class, 'marcarPagado'])->name('trabajadores.bonos.pagar');
    Route::post('trabajadores/bonos/{bono}/pendiente', [TrabajadorBonoController::class, 'marcarPendiente'])->name('trabajadores.bonos.pendiente');
    Route::get('trabajadores/bonos-deuda', [TrabajadorBonoController::class, 'deuda'])->name('trabajadores.bonos.deuda');
});

// ==========================================
// RUTAS DE TIPOS DE HORA (solo Administrador)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador'])->prefix('tipos-hora')->name('tipos-hora.')->group(function () {
    Route::get('/', [TipoHoraController::class, 'index'])->name('index');
    Route::post('/', [TipoHoraController::class, 'store'])->name('store');
    Route::put('/{tipoHora}', [TipoHoraController::class, 'update'])->name('update');
    Route::delete('/{tipoHora}', [TipoHoraController::class, 'destroy'])->name('destroy');
});

// ==========================================
// RUTAS DE NÓMINAS (Administrador, RRHH, Contabilidad)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH|Contabilidad'])->group(function () {
    Route::get('nominas/resumen', [NominaController::class, 'resumen'])->name('nominas.resumen');
    Route::post('nominas', [NominaController::class, 'storeCentral'])->name('nominas.store');
    Route::post('trabajadores/{trabajador}/nominas', [NominaController::class, 'store'])->name('trabajadores.nominas.store');
    Route::delete('nominas/{nomina}', [NominaController::class, 'destroy'])->name('nominas.destroy');
});
// Descarga de nómina: admin/RRHH/Contabilidad o el propio trabajador (autorización en el controlador)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('nominas/{nomina}/download', [NominaController::class, 'download'])->name('nominas.download');
    // Recibo PDF generado: admin/RRHH/Contabilidad o el propio trabajador (autorización en el controlador)
    Route::get('nominas/{nomina}/recibo', [NominaImportacionController::class, 'recibo'])->name('nominas.recibo');
});

// ==========================================
// NÓMINAS — CARGA MASIVA (aditivo, no toca el flujo manual existente)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH|Contabilidad'])->group(function () {
    Route::get('nominas/plantilla', [NominaImportacionController::class, 'plantilla'])->name('nominas.plantilla');
    Route::post('nominas/importar', [NominaImportacionController::class, 'procesar'])->name('nominas.importar');
    Route::post('nominas/importar-gestoria', [NominaImportacionController::class, 'procesarGestoria'])->name('nominas.importar.gestoria');
    Route::get('nominas/mes/{anio}/{mes}', [NominaImportacionController::class, 'porMes'])->name('nominas.mes');
    Route::get('nominas/mes/{anio}/{mes}/exportar', [NominaImportacionController::class, 'exportarMes'])->name('nominas.mes.exportar');
    Route::post('nominas/mes/{anio}/{mes}/enviar', [NominaImportacionController::class, 'enviarMes'])->name('nominas.mes.enviar');
    Route::post('nominas/{nomina}/enviar', [NominaImportacionController::class, 'enviar'])->name('nominas.enviar');
    Route::get('nominas/bitacora', [NominaImportacionController::class, 'bitacora'])->name('nominas.bitacora');
    Route::get('nominas/bitacora/{importacion}', [NominaImportacionController::class, 'bitacoraDetalle'])->name('nominas.bitacora.detalle');
    Route::put('nominas/{nomina}', [NominaImportacionController::class, 'update'])->name('nominas.update');
});

// ==========================================
// EXPORTACIONES A EXCEL (respetan los filtros del listado)
// ==========================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('exportar/ingresos', [\App\Http\Controllers\IngresoController::class, 'exportExcel'])->name('ingresos.export.excel')->middleware('role:Administrador|Contabilidad');
    Route::get('exportar/gastos', [\App\Http\Controllers\GastoController::class, 'exportExcel'])->name('gastos.export.excel')->middleware('role:Administrador|Contabilidad|Encargado');
    Route::get('exportar/facturas', [\App\Http\Controllers\FacturaController::class, 'exportExcel'])->name('facturas.export.excel')->middleware('role:Administrador|Contabilidad');
    Route::get('exportar/contratos', [\App\Http\Controllers\ContratoController::class, 'exportExcel'])->name('contratos.export.excel')->middleware('role:Administrador|Contabilidad|Encargado|RRHH|Auditor');
    Route::get('exportar/obras', [\App\Http\Controllers\ObraController::class, 'exportExcel'])->name('obras.export.excel');
    Route::get('exportar/trabajadores', [\App\Http\Controllers\TrabajadorController::class, 'exportExcel'])->name('trabajadores.export.excel')->middleware('permission:ver_trabajadores');
    Route::get('exportar/bonos', [\App\Http\Controllers\TrabajadorBonoController::class, 'exportExcel'])->name('bonos.export.excel')->middleware('role:Administrador|Contabilidad');
});

// ==========================================
// RUTAS DE TRABAJADORES
// ==========================================
Route::middleware(['auth', 'verified', 'permission:crear_trabajadores'])->group(function () {
    Route::get('trabajadores/create', [TrabajadorController::class, 'create'])->name('trabajadores.create');
    Route::post('trabajadores', [TrabajadorController::class, 'store'])->name('trabajadores.store');
});

Route::middleware(['auth', 'verified', 'permission:ver_trabajadores'])->group(function () {
    // Ver trabajadores
    Route::get('trabajadores', [TrabajadorController::class, 'index'])->name('trabajadores.index');
    Route::get('trabajadores/{trabajador}', [TrabajadorController::class, 'show'])->name('trabajadores.show');
});

Route::middleware(['auth', 'verified', 'permission:editar_trabajadores'])->group(function () {
    Route::get('trabajadores/{trabajador}/edit', [TrabajadorController::class, 'edit'])->name('trabajadores.edit');
    Route::put('trabajadores/{trabajador}', [TrabajadorController::class, 'update'])->name('trabajadores.update');
    Route::patch('trabajadores/{trabajador}', [TrabajadorController::class, 'update']);

    // Documentos del trabajador
    Route::post('trabajadores/{trabajador}/documentos', [TrabajadorController::class, 'storeDocumento'])
        ->name('trabajadores.documentos.store');
    Route::delete('trabajadores/{trabajador}/documentos/{documento}', [TrabajadorController::class, 'destroyDocumento'])
        ->name('trabajadores.documentos.destroy');

    // Formaciones del trabajador
    Route::post('trabajadores/{trabajador}/formaciones', [TrabajadorController::class, 'storeFormacion'])
        ->name('trabajadores.formaciones.store');
    Route::delete('trabajadores/{trabajador}/formaciones/{formacion}', [TrabajadorController::class, 'destroyFormacion'])
        ->name('trabajadores.formaciones.destroy');

    // Historial disciplinario
    Route::post('trabajadores/{trabajador}/historial', [TrabajadorController::class, 'storeHistorial'])
        ->name('trabajadores.historial.store');

    // Dar de baja
    Route::post('trabajadores/{trabajador}/baja', [TrabajadorController::class, 'darBaja'])
        ->name('trabajadores.baja');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_trabajadores'])->group(function () {
    Route::delete('trabajadores/{trabajador}', [TrabajadorController::class, 'destroy'])->name('trabajadores.destroy');
});

// AJAX para obtener bonos de un trabajador (después de la ruta {trabajador})
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad'])->group(function () {
    Route::get('trabajadores/{trabajador}/bonos', [TrabajadorBonoController::class, 'porTrabajador'])->name('trabajadores.bonos.por-trabajador');
});

// ==========================================
// RUTAS DE CUADRILLAS
// ==========================================
Route::middleware(['auth', 'verified', 'permission:ver_cuadrillas'])->group(function () {
    Route::get('cuadrillas', [CuadrillaController::class, 'index'])->name('cuadrillas.index');
    Route::get('cuadrillas/{cuadrilla}', [CuadrillaController::class, 'show'])->name('cuadrillas.show');
});

Route::middleware(['auth', 'verified', 'permission:crear_cuadrillas'])->group(function () {
    Route::get('cuadrillas/create', [CuadrillaController::class, 'create'])->name('cuadrillas.create');
    Route::post('cuadrillas', [CuadrillaController::class, 'store'])->name('cuadrillas.store');
});

Route::middleware(['auth', 'verified', 'permission:editar_cuadrillas'])->group(function () {
    Route::get('cuadrillas/{cuadrilla}/edit', [CuadrillaController::class, 'edit'])->name('cuadrillas.edit');
    Route::put('cuadrillas/{cuadrilla}', [CuadrillaController::class, 'update'])->name('cuadrillas.update');
    Route::patch('cuadrillas/{cuadrilla}', [CuadrillaController::class, 'update']);

    // Gestión de trabajadores en cuadrilla
    Route::post('cuadrillas/{cuadrilla}/trabajadores', [CuadrillaController::class, 'addTrabajador'])
        ->name('cuadrillas.trabajadores.add');
    Route::delete('cuadrillas/{cuadrilla}/trabajadores/{trabajador}', [CuadrillaController::class, 'removeTrabajador'])
        ->name('cuadrillas.trabajadores.remove');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_cuadrillas'])->group(function () {
    Route::delete('cuadrillas/{cuadrilla}', [CuadrillaController::class, 'destroy'])->name('cuadrillas.destroy');
});

// ==========================================
// RUTAS DE CLIENTES
// ==========================================
Route::middleware(['auth', 'verified', 'permission:ver_clientes'])->group(function () {
    Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
});

Route::middleware(['auth', 'verified', 'permission:crear_clientes'])->group(function () {
    Route::get('clientes/create', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store');
});

Route::middleware(['auth', 'verified', 'permission:editar_clientes'])->group(function () {
    Route::get('clientes/{cliente}/edit', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::patch('clientes/{cliente}', [ClienteController::class, 'update']);

    // Interacciones CRM
    Route::post('clientes/{cliente}/interacciones', [ClienteController::class, 'storeInteraccion'])
        ->name('clientes.interacciones.store');

    // CRUD de emails adicionales de cliente
    Route::post('clientes/{cliente}/emails', [ClienteEmailController::class, 'store'])
        ->name('clientes.emails.store');
    Route::put('cliente-emails/{emailAdicional}', [ClienteEmailController::class, 'update'])
        ->name('clientes.emails.update');
    Route::delete('cliente-emails/{emailAdicional}', [ClienteEmailController::class, 'destroy'])
        ->name('clientes.emails.destroy');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_clientes'])->group(function () {
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
});

// ==========================================
// RUTAS DE OBRAS
// ==========================================
Route::middleware(['auth', 'verified', 'permission:crear_obras'])->group(function () {
    Route::get('obras/create', [ObraController::class, 'create'])->name('obras.create');
    Route::post('obras', [ObraController::class, 'store'])->name('obras.store');
});

Route::middleware(['auth', 'verified', 'permission:ver_obras'])->group(function () {
    Route::get('obras', [ObraController::class, 'index'])->name('obras.index');
    Route::get('obras/{obra}', [ObraController::class, 'show'])->name('obras.show');
    Route::get('obras/{obra}/export-equipo', [ObraController::class, 'exportEquipo'])->name('obras.equipo.export');
});

Route::middleware(['auth', 'verified', 'permission:editar_obras'])->group(function () {
    Route::get('obras/{obra}/edit', [ObraController::class, 'edit'])->name('obras.edit');
    Route::put('obras/{obra}', [ObraController::class, 'update'])->name('obras.update');
    Route::patch('obras/{obra}', [ObraController::class, 'update']);

    // Cambio de estado
    Route::post('obras/{obra}/cambiar-estado', [ObraController::class, 'cambiarEstado'])
        ->name('obras.cambiar-estado');

    // Hitos
    Route::post('obras/{obra}/hitos', [ObraController::class, 'storeHito'])
        ->name('obras.hitos.store');
    Route::post('obras/{obra}/hitos/generar-ingresos', [ObraController::class, 'generarIngresosHitos'])
        ->name('obras.hitos.generar-ingresos');
    Route::post('obras/{obra}/hitos/{hito}/completar', [ObraController::class, 'completarHito'])
        ->name('obras.hitos.completar');
    Route::delete('obras/{obra}/hitos/{hito}', [ObraController::class, 'destroyHito'])
        ->name('obras.hitos.destroy');

    // Documentos
    Route::post('obras/{obra}/documentos', [ObraController::class, 'storeDocumento'])
        ->name('obras.documentos.store');
    Route::delete('obras/{obra}/documentos/{documento}', [ObraController::class, 'destroyDocumento'])
        ->name('obras.documentos.destroy');

    // Asignación de trabajadores
    Route::post('obras/{obra}/trabajadores', [ObraController::class, 'addTrabajador'])
        ->name('obras.trabajadores.add');
    Route::delete('obras/{obra}/trabajadores/{trabajador}', [ObraController::class, 'removeTrabajador'])
        ->name('obras.trabajadores.remove');

    // Asignación de cuadrillas
    Route::post('obras/{obra}/cuadrillas', [ObraController::class, 'addCuadrilla'])
        ->name('obras.cuadrillas.add');
    Route::delete('obras/{obra}/cuadrillas/{cuadrilla}', [ObraController::class, 'removeCuadrilla'])
        ->name('obras.cuadrillas.remove');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_obras'])->group(function () {
    Route::delete('obras/{obra}', [ObraController::class, 'destroy'])->name('obras.destroy');
});

// ==========================================
// RUTAS DE CONCEPTOS DE PRODUCCIÓN (sub-recurso de obras)
// ==========================================
Route::middleware(['auth', 'verified', 'permission:editar_obras'])->prefix('obras/{obra}/conceptos')->name('obras.conceptos.')->group(function () {
    Route::get('/', [ObraConceptoProduccionController::class, 'index'])->name('index');
    Route::post('/', [ObraConceptoProduccionController::class, 'store'])->name('store');
    Route::put('/{concepto}', [ObraConceptoProduccionController::class, 'update'])->name('update');
    Route::delete('/{concepto}', [ObraConceptoProduccionController::class, 'destroy'])->name('destroy');
    Route::post('/duplicate/{obraOrigen}', [ObraConceptoProduccionController::class, 'duplicate'])->name('duplicate');
});

// ==========================================
// RUTAS DE DISCREPANCIAS DE VALORACIÓN (sub-recurso de obras)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad'])->prefix('obras/{obra}/discrepancias')->name('obras.discrepancias.')->group(function () {
    Route::get('/', [ObraDiscrepanciaController::class, 'index'])->name('index');
    Route::get('/create', [ObraDiscrepanciaController::class, 'create'])->name('create');
    Route::post('/', [ObraDiscrepanciaController::class, 'store'])->name('store');
    Route::get('/{discrepancia}', [ObraDiscrepanciaController::class, 'show'])->name('show');
    Route::get('/{discrepancia}/edit', [ObraDiscrepanciaController::class, 'edit'])->name('edit');
    Route::put('/{discrepancia}', [ObraDiscrepanciaController::class, 'update'])->name('update');
    Route::post('/{discrepancia}/resolver', [ObraDiscrepanciaController::class, 'marcarResuelto'])->name('resolver');
});

// ==========================================
// RUTAS DE FICHAJES
// ==========================================
// Configuración de recordatorios (debe ir antes de fichajes/{fichaje})
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH'])->group(function () {
    Route::get('fichajes/configuracion', [FichajeController::class, 'configuracion'])->name('fichajes.configuracion');
    Route::post('fichajes/configuracion', [FichajeController::class, 'guardarConfiguracion'])->name('fichajes.configuracion.guardar');
});
Route::middleware(['auth', 'verified', 'permission:crear_fichajes'])->group(function () {
    Route::get('fichajes/create', [FichajeController::class, 'create'])->name('fichajes.create');
    Route::post('fichajes', [FichajeController::class, 'store'])->name('fichajes.store');

    // AJAX: Obtener obras asignadas a un trabajador
    Route::get('fichajes/ajax/obras-trabajador/{trabajador}', [FichajeController::class, 'getObrasTrabajador'])
        ->name('fichajes.obras-trabajador');

    // API para check-in/check-out desde móvil
    Route::post('fichajes/check-in', [FichajeController::class, 'checkIn'])->name('fichajes.check-in');
    Route::post('fichajes/check-out', [FichajeController::class, 'checkOut'])->name('fichajes.check-out');
});

Route::middleware(['auth', 'verified', 'permission:ver_fichajes'])->group(function () {
    Route::get('fichajes', [FichajeController::class, 'index'])->name('fichajes.index');
    Route::get('fichajes/resumen', [FichajeController::class, 'resumen'])->name('fichajes.resumen');
    Route::get('fichajes/export/excel', [FichajeController::class, 'exportExcel'])->name('fichajes.export.excel');
    Route::get('fichajes/export/pdf', [FichajeController::class, 'exportPdf'])->name('fichajes.export.pdf');
    Route::get('fichajes/{fichaje}', [FichajeController::class, 'show'])->name('fichajes.show');
    Route::get('fichajes/{fichaje}/details', [FichajeController::class, 'getDetails'])->name('fichajes.details');
});

Route::middleware(['auth', 'verified', 'permission:editar_fichajes'])->group(function () {
    Route::get('fichajes/{fichaje}/edit', [FichajeController::class, 'edit'])->name('fichajes.edit');
    Route::put('fichajes/{fichaje}', [FichajeController::class, 'update'])->name('fichajes.update');
    Route::patch('fichajes/{fichaje}', [FichajeController::class, 'update']);
});

Route::middleware(['auth', 'verified', 'permission:validar_fichajes'])->group(function () {
    Route::post('fichajes/{fichaje}/validar', [FichajeController::class, 'validar'])->name('fichajes.validar');
    Route::post('fichajes/validar-multiple', [FichajeController::class, 'validarMultiple'])->name('fichajes.validar-multiple');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_fichajes'])->group(function () {
    Route::delete('fichajes/{fichaje}', [FichajeController::class, 'destroy'])->name('fichajes.destroy');
});

// ==========================================
// RUTAS DE PARTES DIARIOS
// ==========================================
Route::middleware(['auth', 'verified', 'permission:crear_partes'])->group(function () {
    Route::get('partes-diarios/create', [ParteDiarioController::class, 'create'])->name('partes-diarios.create');
    Route::post('partes-diarios', [ParteDiarioController::class, 'store'])->name('partes-diarios.store');
});

Route::middleware(['auth', 'verified', 'permission:ver_partes'])->group(function () {
    Route::get('partes-diarios', [ParteDiarioController::class, 'index'])->name('partes-diarios.index');
    Route::get('partes-diarios/ajax/trabajadores-obra/{obra}', [ParteDiarioController::class, 'getTrabajadoresObra'])
        ->name('partes-diarios.trabajadores-obra');
    Route::get('partes-diarios/{partes_diario}', [ParteDiarioController::class, 'show'])->name('partes-diarios.show');
});

Route::middleware(['auth', 'verified', 'permission:editar_partes'])->group(function () {
    Route::get('partes-diarios/{partes_diario}/edit', [ParteDiarioController::class, 'edit'])->name('partes-diarios.edit');
    Route::put('partes-diarios/{partes_diario}', [ParteDiarioController::class, 'update'])->name('partes-diarios.update');
    Route::patch('partes-diarios/{partes_diario}', [ParteDiarioController::class, 'update']);

    // Completar parte
    Route::post('partes-diarios/{partes_diario}/completar', [ParteDiarioController::class, 'completar'])
        ->name('partes-diarios.completar');

    // Duplicar parte
    Route::post('partes-diarios/{partes_diario}/duplicar', [ParteDiarioController::class, 'duplicar'])
        ->name('partes-diarios.duplicar');

    // Gestión de trabajadores en parte
    Route::post('partes-diarios/{partes_diario}/trabajadores', [ParteDiarioController::class, 'addTrabajador'])
        ->name('partes-diarios.trabajadores.add');
    Route::delete('partes-diarios/{partes_diario}/trabajadores/{trabajador}', [ParteDiarioController::class, 'removeTrabajador'])
        ->name('partes-diarios.trabajadores.remove');

    // Gestión de documentos en parte
    Route::post('partes-diarios/{partes_diario}/documentos', [ParteDiarioController::class, 'storeDocumento'])
        ->name('partes-diarios.documentos.store');
    Route::delete('partes-diarios/{partes_diario}/documentos/{documento}', [ParteDiarioController::class, 'destroyDocumento'])
        ->name('partes-diarios.documentos.destroy');
});

Route::middleware(['auth', 'verified', 'permission:validar_partes'])->group(function () {
    Route::post('partes-diarios/{partes_diario}/validar', [ParteDiarioController::class, 'validar'])
        ->name('partes-diarios.validar');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_partes'])->group(function () {
    Route::delete('partes-diarios/{partes_diario}', [ParteDiarioController::class, 'destroy'])->name('partes-diarios.destroy');
});

// ==========================================
// RUTAS DE MAQUINARIA
// ==========================================
Route::middleware(['auth', 'verified', 'permission:crear_maquinaria'])->group(function () {
    Route::get('maquinaria/create', [MaquinariaController::class, 'create'])->name('maquinaria.create');
    Route::post('maquinaria', [MaquinariaController::class, 'store'])->name('maquinaria.store');
});

Route::middleware(['auth', 'verified', 'permission:ver_maquinaria'])->group(function () {
    Route::get('maquinaria', [MaquinariaController::class, 'index'])->name('maquinaria.index');
    Route::get('maquinaria/{maquinaria}', [MaquinariaController::class, 'show'])->name('maquinaria.show');
});

Route::middleware(['auth', 'verified', 'permission:editar_maquinaria'])->group(function () {
    Route::get('maquinaria/{maquinaria}/edit', [MaquinariaController::class, 'edit'])->name('maquinaria.edit');
    Route::put('maquinaria/{maquinaria}', [MaquinariaController::class, 'update'])->name('maquinaria.update');
    Route::patch('maquinaria/{maquinaria}', [MaquinariaController::class, 'update']);

    // Cambio de estado
    Route::post('maquinaria/{maquinaria}/cambiar-estado', [MaquinariaController::class, 'cambiarEstado'])
        ->name('maquinaria.cambiar-estado');

    // Asignaciones
    Route::post('maquinaria/{maquinaria}/asignar-obra', [MaquinariaController::class, 'asignarObra'])
        ->name('maquinaria.asignar-obra');
    Route::post('maquinaria/{maquinaria}/desasignar-obra', [MaquinariaController::class, 'desasignarObra'])
        ->name('maquinaria.desasignar-obra');

    // Inspecciones
    Route::get('maquinaria/{maquinaria}/inspecciones/create', [MaquinariaController::class, 'createInspeccion'])
        ->name('maquinaria.inspecciones.create');
    Route::post('maquinaria/{maquinaria}/inspecciones', [MaquinariaController::class, 'storeInspeccion'])
        ->name('maquinaria.inspecciones.store');

    // Mantenimientos
    Route::post('maquinaria/{maquinaria}/mantenimientos', [MaquinariaController::class, 'storeMantenimiento'])
        ->name('maquinaria.mantenimientos.store');
    Route::delete('maquinaria/{maquinaria}/mantenimientos/{mantenimiento}', [MaquinariaController::class, 'destroyMantenimiento'])
        ->name('maquinaria.mantenimientos.destroy');
});

// Documentos de Maquinaria (puede subir quien tenga editar_maquinaria O subir_documentos_maquinaria)
Route::middleware(['auth', 'verified', 'permission:editar_maquinaria|subir_documentos_maquinaria'])->group(function () {
    Route::post('maquinaria/{maquinaria}/documentos', [MaquinariaController::class, 'storeDocumento'])
        ->name('maquinaria.documentos.store');
    Route::delete('maquinaria/{maquinaria}/documentos/{documento}', [MaquinariaController::class, 'destroyDocumento'])
        ->name('maquinaria.documentos.destroy');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_maquinaria'])->group(function () {
    Route::delete('maquinaria/{maquinaria}', [MaquinariaController::class, 'destroy'])->name('maquinaria.destroy');
});

// ==========================================
// RUTAS DE VEHÍCULOS
// ==========================================
Route::middleware(['auth', 'verified', 'permission:crear_vehiculos'])->group(function () {
    Route::get('vehiculos/create', [VehiculoController::class, 'create'])->name('vehiculos.create');
    Route::post('vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store');
});

Route::middleware(['auth', 'verified', 'permission:ver_vehiculos'])->group(function () {
    Route::get('vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');
    Route::get('vehiculos/{vehiculo}', [VehiculoController::class, 'show'])->name('vehiculos.show');
});

Route::middleware(['auth', 'verified', 'permission:editar_vehiculos'])->group(function () {
    Route::get('vehiculos/{vehiculo}/edit', [VehiculoController::class, 'edit'])->name('vehiculos.edit');
    Route::put('vehiculos/{vehiculo}', [VehiculoController::class, 'update'])->name('vehiculos.update');
    Route::patch('vehiculos/{vehiculo}', [VehiculoController::class, 'update']);

    // Cambio de estado
    Route::post('vehiculos/{vehiculo}/cambiar-estado', [VehiculoController::class, 'cambiarEstado'])
        ->name('vehiculos.cambiar-estado');

    // Documentos
    Route::post('vehiculos/{vehiculo}/documentos', [VehiculoController::class, 'storeDocumento'])
        ->name('vehiculos.documentos.store');
    Route::delete('vehiculos/{vehiculo}/documentos/{documento}', [VehiculoController::class, 'destroyDocumento'])
        ->name('vehiculos.documentos.destroy');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_vehiculos'])->group(function () {
    Route::delete('vehiculos/{vehiculo}', [VehiculoController::class, 'destroy'])->name('vehiculos.destroy');
});

// ==========================================
// RUTAS DE SUBCONTRATAS
// ==========================================
Route::middleware(['auth', 'verified', 'permission:crear_subcontratas'])->group(function () {
    Route::get('subcontratas/create', [SubcontrataController::class, 'create'])->name('subcontratas.create');
    Route::post('subcontratas', [SubcontrataController::class, 'store'])->name('subcontratas.store');
});

Route::middleware(['auth', 'verified', 'permission:ver_subcontratas'])->group(function () {
    Route::get('subcontratas', [SubcontrataController::class, 'index'])->name('subcontratas.index');
    Route::get('subcontratas/{subcontrata}', [SubcontrataController::class, 'show'])->name('subcontratas.show');
});

Route::middleware(['auth', 'verified', 'permission:editar_subcontratas'])->group(function () {
    Route::get('subcontratas/{subcontrata}/edit', [SubcontrataController::class, 'edit'])->name('subcontratas.edit');
    Route::put('subcontratas/{subcontrata}', [SubcontrataController::class, 'update'])->name('subcontratas.update');
    Route::patch('subcontratas/{subcontrata}', [SubcontrataController::class, 'update']);

    // Estado y homologación
    Route::post('subcontratas/{subcontrata}/toggle-activa', [SubcontrataController::class, 'toggleActiva'])
        ->name('subcontratas.toggle-activa');
    Route::post('subcontratas/{subcontrata}/toggle-homologada', [SubcontrataController::class, 'toggleHomologada'])
        ->name('subcontratas.toggle-homologada');

    // Documentos CAE
    Route::post('subcontratas/{subcontrata}/documentos-cae', [SubcontrataController::class, 'storeDocumentoCae'])
        ->name('subcontratas.documentos-cae.store');
    Route::post('subcontratas/{subcontrata}/documentos-cae/{documento}/verificar', [SubcontrataController::class, 'verificarDocumentoCae'])
        ->name('subcontratas.documentos-cae.verificar');
    Route::delete('subcontratas/{subcontrata}/documentos-cae/{documento}', [SubcontrataController::class, 'destroyDocumentoCae'])
        ->name('subcontratas.documentos-cae.destroy');

    // Asignación a obras
    Route::post('subcontratas/{subcontrata}/obras', [SubcontrataController::class, 'addObra'])
        ->name('subcontratas.obras.add');
    Route::put('subcontratas/{subcontrata}/obras/{obra}', [SubcontrataController::class, 'updateObraAsignacion'])
        ->name('subcontratas.obras.update');
    Route::delete('subcontratas/{subcontrata}/obras/{obra}', [SubcontrataController::class, 'removeObra'])
        ->name('subcontratas.obras.remove');

    // Documentos por obra
    Route::post('subcontratas/{subcontrata}/obras/{obra}/documentos', [SubcontrataController::class, 'storeDocumentoObra'])
        ->name('subcontratas.obras.documentos.store');
    Route::delete('subcontratas/{subcontrata}/obras/{obra}/documentos/{documento}', [SubcontrataController::class, 'destroyDocumentoObra'])
        ->name('subcontratas.obras.documentos.destroy');
});

Route::middleware(['auth', 'verified', 'permission:eliminar_subcontratas'])->group(function () {
    Route::delete('subcontratas/{subcontrata}', [SubcontrataController::class, 'destroy'])->name('subcontratas.destroy');
});

// ==========================================
// RUTAS DE CATEGORÍAS DE GASTOS (solo Administrador)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador'])->prefix('gasto-categorias')->name('gasto-categorias.')->group(function () {
    Route::get('/', [GastoCategoriaController::class, 'index'])->name('index');
    Route::post('/', [GastoCategoriaController::class, 'store'])->name('store');
    Route::put('/{categoria}', [GastoCategoriaController::class, 'update'])->name('update');
    Route::delete('/{categoria}', [GastoCategoriaController::class, 'destroy'])->name('destroy');
});

// ==========================================
// RUTAS DE INGRESOS (Administrador + Contabilidad)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad'])->group(function () {
    Route::resource('ingresos', IngresoController::class);
    Route::post('ingresos/{ingreso}/marcar-cobrado', [IngresoController::class, 'marcarCobrado'])->name('ingresos.marcar-cobrado');
    Route::post('ingresos/{ingreso}/marcar-pendiente', [IngresoController::class, 'marcarPendiente'])->name('ingresos.marcar-pendiente');

    // Resumen de impuestos (IVA, IRPF, Seguridad Social)
    Route::get('impuestos/resumen', [\App\Http\Controllers\ImpuestosController::class, 'resumen'])->name('impuestos.resumen');
});

// ==========================================
// RUTAS DE GASTOS (Administrador + Contabilidad + Encargado)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad|Encargado'])->group(function () {
    Route::resource('gastos', GastoController::class);
    Route::post('gastos/{gasto}/marcar-pagado', [GastoController::class, 'marcarPagado'])->name('gastos.marcar-pagado');
    Route::post('gastos/{gasto}/marcar-pendiente', [GastoController::class, 'marcarPendiente'])->name('gastos.marcar-pendiente');
});

// ==========================================
// RUTAS DE TIPOS DE CONTRATO (solo Administrador)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador'])->prefix('contrato-tipos')->name('contrato-tipos.')->group(function () {
    Route::get('/', [ContratoTipoController::class, 'index'])->name('index');
    Route::post('/', [ContratoTipoController::class, 'store'])->name('store');
    Route::put('/{tipo}', [ContratoTipoController::class, 'update'])->name('update');
    Route::delete('/{tipo}', [ContratoTipoController::class, 'destroy'])->name('destroy');
});

// ==========================================
// RUTAS DE CONTRATOS
// ==========================================

// Crear y editar contratos (Admin, Contabilidad) - IMPORTANTE: Debe ir ANTES de {contrato}
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad'])->group(function () {
    Route::get('contratos/create', [ContratoController::class, 'create'])->name('contratos.create');
    Route::post('contratos', [ContratoController::class, 'store'])->name('contratos.store');
    Route::get('contratos/{contrato}/edit', [ContratoController::class, 'edit'])->name('contratos.edit');
    Route::put('contratos/{contrato}', [ContratoController::class, 'update'])->name('contratos.update');
    Route::patch('contratos/{contrato}', [ContratoController::class, 'update']);

    // Cambio de estado
    Route::post('contratos/{contrato}/activar', [ContratoController::class, 'activar'])->name('contratos.activar');
    Route::post('contratos/{contrato}/cancelar', [ContratoController::class, 'cancelar'])->name('contratos.cancelar');
    Route::post('contratos/{contrato}/marcar-vencido', [ContratoController::class, 'marcarVencido'])->name('contratos.marcar-vencido');
    Route::post('contratos/{contrato}/reactivar', [ContratoController::class, 'reactivar'])->name('contratos.reactivar');

    // Gestión de garantías
    Route::post('contratos/{contrato}/liberar-garantia', [ContratoController::class, 'liberarGarantia'])->name('contratos.liberar-garantia');
});

// Ver contratos (Admin, Contabilidad, Encargado, RRHH, Auditor)
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad|Encargado|RRHH|Auditor'])->group(function () {
    Route::get('contratos', [ContratoController::class, 'index'])->name('contratos.index');
    Route::get('contratos/{contrato}', [ContratoController::class, 'show'])->name('contratos.show');
});

// Eliminar contratos (solo Admin)
Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::delete('contratos/{contrato}', [ContratoController::class, 'destroy'])->name('contratos.destroy');
});

// ==========================================
// RUTAS DE FACTURAS
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad'])->group(function () {
    // CRUD de facturas
    Route::resource('facturas', FacturaController::class);

    // Acciones de estado
    Route::post('facturas/{factura}/emitir', [FacturaController::class, 'emitir'])->name('facturas.emitir');
    Route::post('facturas/{factura}/numero', [FacturaController::class, 'actualizarNumero'])->name('facturas.numero');
    Route::post('facturas/{factura}/marcar-enviada', [FacturaController::class, 'marcarEnviada'])->name('facturas.marcar-enviada');
    Route::post('facturas/{factura}/enviar', [FacturaController::class, 'enviar'])->name('facturas.enviar');
    Route::post('facturas/{factura}/cobrar', [FacturaController::class, 'cobrar'])->name('facturas.cobrar');
    Route::post('facturas/{factura}/anular', [FacturaController::class, 'anular'])->name('facturas.anular');

    // PDF
    Route::get('facturas/{factura}/pdf', [FacturaController::class, 'generarPdf'])->name('facturas.pdf');
    Route::get('facturas/{factura}/descargar-pdf', [FacturaController::class, 'descargarPdf'])->name('facturas.descargar-pdf');

    // Auxiliar AJAX
    Route::get('facturas/ajax/cliente-obra/{obra}', [FacturaController::class, 'getClienteObra'])->name('facturas.cliente-obra');

    // Get emails disponibles del cliente de una factura
    Route::get('facturas/{factura}/emails-cliente', [FacturaController::class, 'getClienteEmails'])
        ->name('facturas.emails-cliente');
});

// ==========================================
// RUTAS DE EPIs (Equipos de Protección Individual)
// ==========================================

// Catálogo de EPIs (Admin, RRHH, Encargado)
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH|Encargado'])->group(function () {
    Route::resource('epi-catalogo', EpiCatalogoController::class)->except(['show']);
});

// Inventario de EPIs - CRUD y acciones (Admin, RRHH, Encargado)
// IMPORTANTE: Las rutas create/edit van ANTES de las rutas con parámetros {epiInventario}
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH|Encargado'])->group(function () {
    Route::get('epi-inventario/create', [EpiInventarioController::class, 'create'])->name('epi-inventario.create');
    Route::post('epi-inventario', [EpiInventarioController::class, 'store'])->name('epi-inventario.store');
    Route::get('epi-inventario/{epiInventario}/edit', [EpiInventarioController::class, 'edit'])->name('epi-inventario.edit');
    Route::put('epi-inventario/{epiInventario}', [EpiInventarioController::class, 'update'])->name('epi-inventario.update');
    Route::delete('epi-inventario/{epiInventario}', [EpiInventarioController::class, 'destroy'])->name('epi-inventario.destroy');

    // Acciones de entrega y devolución
    Route::post('epi-inventario/{epiInventario}/entregar', [EpiInventarioController::class, 'entregarEpi'])->name('epi-inventario.entregar');
    Route::post('epi-inventario/{epiInventario}/devolver', [EpiInventarioController::class, 'devolverEpi'])->name('epi-inventario.devolver');
    Route::post('epi-inventario/{epiInventario}/revisiones', [EpiInventarioController::class, 'registrarRevision'])->name('epi-inventario.revisiones.store');
    Route::post('epi-inventario/{epiInventario}/baja', [EpiInventarioController::class, 'darDeBaja'])->name('epi-inventario.baja');

    // Documentos de EPI
    Route::post('epi-inventario/{epiInventario}/documentos', [EpiInventarioController::class, 'storeDocumento'])->name('epi-inventario.documentos.store');
    Route::delete('epi-inventario/{epiInventario}/documentos/{documento}', [EpiInventarioController::class, 'destroyDocumento'])->name('epi-inventario.documentos.destroy');
});

// Inventario de EPIs - Ver (Admin, RRHH, Encargado, Contabilidad)
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH|Encargado|Contabilidad'])->group(function () {
    Route::get('epi-inventario', [EpiInventarioController::class, 'index'])->name('epi-inventario.index');
    Route::get('epi-inventario/{epiInventario}', [EpiInventarioController::class, 'show'])->name('epi-inventario.show');
    Route::get('epi-entregas', [EpiInventarioController::class, 'historialEntregas'])->name('epi-entregas.index');
});

// ==========================================
// RUTAS DE FORMACIONES
// ==========================================
use App\Http\Controllers\FormacionTipoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AlertaConfiguracionController;
use App\Http\Controllers\CaducidadGeneralController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CumpleanosConfiguracionController;

Route::middleware(['auth', 'verified', 'role:Administrador|RRHH'])->group(function () {
    Route::resource('formacion-tipos', FormacionTipoController::class);
});

// ==========================================
// RUTAS DE ALERTAS Y CADUCIDADES
// ==========================================

// Configuración de Alertas (Admin, RRHH) - DEBE IR ANTES de las rutas con parámetros
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH'])->prefix('alertas/configuracion')->name('alertas.configuracion.')->group(function () {
    Route::get('/', [AlertaConfiguracionController::class, 'index'])->name('index');
    Route::put('/{configuracion}', [AlertaConfiguracionController::class, 'update'])->name('update');
    Route::post('/{configuracion}/toggle', [AlertaConfiguracionController::class, 'toggleActiva'])->name('toggle');
});

// Configuración de Emails de Cumpleaños (Admin, RRHH)
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH'])->prefix('cumpleanos/configuracion')->name('cumpleanos.configuracion.')->group(function () {
    Route::get('/', [CumpleanosConfiguracionController::class, 'index'])->name('index');
    Route::put('/', [CumpleanosConfiguracionController::class, 'update'])->name('update');
    Route::post('/toggle', [CumpleanosConfiguracionController::class, 'toggleActiva'])->name('toggle');
    Route::post('/adjunto', [CumpleanosConfiguracionController::class, 'subirAdjunto'])->name('adjunto.subir');
    Route::delete('/adjunto', [CumpleanosConfiguracionController::class, 'eliminarAdjunto'])->name('adjunto.eliminar');
    Route::post('/prueba', [CumpleanosConfiguracionController::class, 'enviarPrueba'])->name('prueba');
});

// Alertas - Todos los usuarios autenticados
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::post('alertas/marcar-leidas-multiple', [AlertaController::class, 'marcarLeidasMultiple'])->name('alertas.marcar-leidas-multiple');
    Route::get('alertas/{alerta}', [AlertaController::class, 'show'])->name('alertas.show');
    Route::post('alertas/{alerta}/marcar-leida', [AlertaController::class, 'marcarLeida'])->name('alertas.marcar-leida');
    Route::post('alertas/{alerta}/marcar-resuelta', [AlertaController::class, 'marcarResuelta'])->name('alertas.marcar-resuelta');

    // AJAX endpoints
    Route::get('alertas-ajax/contador', [AlertaController::class, 'contadorNoLeidas'])->name('alertas.contador');
    Route::get('alertas-ajax/recientes', [AlertaController::class, 'recientes'])->name('alertas.recientes');
});

// Caducidades Generales (Admin, RRHH)
Route::middleware(['auth', 'verified', 'role:Administrador|RRHH'])->group(function () {
    Route::resource('caducidades-generales', CaducidadGeneralController::class);
});

// ==========================================
// RUTAS DE AUDITORÍA
// ==========================================
Route::middleware(['auth', 'verified', 'permission:ver_auditoria'])->group(function () {
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    Route::get('auditoria/exportar', [AuditoriaController::class, 'exportar'])->name('auditoria.exportar');
    Route::get('auditoria/{auditoria}', [AuditoriaController::class, 'show'])->name('auditoria.show');
});

// ==========================================
// RUTAS DE DOCUMENTOS DE EMPRESA (solo Administrador)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::get('documentos-empresa', [DocumentoEmpresaController::class, 'index'])->name('documentos-empresa.index');
    Route::get('documentos-empresa/create', [DocumentoEmpresaController::class, 'create'])->name('documentos-empresa.create');
    Route::post('documentos-empresa', [DocumentoEmpresaController::class, 'store'])->name('documentos-empresa.store');
    Route::get('documentos-empresa/{documentos_empresa}', [DocumentoEmpresaController::class, 'show'])->name('documentos-empresa.show');
    Route::get('documentos-empresa/{documentos_empresa}/edit', [DocumentoEmpresaController::class, 'edit'])->name('documentos-empresa.edit');
    Route::put('documentos-empresa/{documentos_empresa}', [DocumentoEmpresaController::class, 'update'])->name('documentos-empresa.update');
    Route::delete('documentos-empresa/{documentos_empresa}', [DocumentoEmpresaController::class, 'destroy'])->name('documentos-empresa.destroy');
    Route::get('documentos-empresa/{documentos_empresa}/descargar', [DocumentoEmpresaController::class, 'descargar'])->name('documentos-empresa.descargar');
});

// ==========================================
// RUTAS DE ADMINISTRACIÓN
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard Admin - Vista principal
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Dashboard Admin - API para widgets AJAX
    Route::prefix('dashboard/api')->name('dashboard.api.')->group(function () {
        Route::get('/kpis', [AdminDashboardController::class, 'getKpisFiltered'])->name('kpis');
        Route::get('/rentabilidad-mensual', [AdminDashboardController::class, 'getRentabilidadMensual'])->name('rentabilidad-mensual');
        Route::get('/flujo-caja', [AdminDashboardController::class, 'getFlujoCaja'])->name('flujo-caja');
        Route::get('/rentabilidad-obras', [AdminDashboardController::class, 'getRentabilidadObras'])->name('rentabilidad-obras');
        Route::get('/rentabilidad-cuadrillas', [AdminDashboardController::class, 'getRentabilidadCuadrillas'])->name('rentabilidad-cuadrillas');
        Route::get('/cobros-pendientes', [AdminDashboardController::class, 'getCobrosPendientes'])->name('cobros-pendientes');
        Route::get('/obras-riesgo', [AdminDashboardController::class, 'getObrasRiesgo'])->name('obras-riesgo');
        Route::get('/produccion', [AdminDashboardController::class, 'getProduccion'])->name('produccion');
        Route::get('/alertas-criticas', [AdminDashboardController::class, 'getAlertasCriticas'])->name('alertas-criticas');
    });

    // Gestión de Usuarios
    Route::resource('usuarios', AdminUserController::class)->parameters(['usuarios' => 'user']);
});

// ==========================================
// RUTAS DE ENCARGADO
// ==========================================
use App\Http\Controllers\Encargado\DashboardController as EncargadoDashboardController;

Route::middleware(['auth', 'verified', 'role:Encargado'])->prefix('encargado')->name('encargado.')->group(function () {
    // Dashboard Encargado - Vista principal
    Route::get('/dashboard', [EncargadoDashboardController::class, 'index'])->name('dashboard');

    // Dashboard Encargado - APIs para widgets AJAX
    Route::prefix('dashboard/api')->name('dashboard.api.')->group(function () {
        Route::get('/kpis', [EncargadoDashboardController::class, 'getKpis'])->name('kpis');
        Route::get('/mis-obras', [EncargadoDashboardController::class, 'getMisObras'])->name('mis-obras');
        Route::get('/produccion-diaria', [EncargadoDashboardController::class, 'getProduccionDiaria'])->name('produccion-diaria');
        Route::get('/metricas-estado', [EncargadoDashboardController::class, 'getMetricasPorEstado'])->name('metricas-estado');
        Route::get('/horas-trabajadores', [EncargadoDashboardController::class, 'getHorasTrabajadores'])->name('horas-trabajadores');
        Route::get('/maquinaria-asignada', [EncargadoDashboardController::class, 'getMaquinariaAsignada'])->name('maquinaria-asignada');
        Route::get('/calendario-semanal', [EncargadoDashboardController::class, 'getCalendarioSemanal'])->name('calendario-semanal');
        Route::get('/partes-pendientes', [EncargadoDashboardController::class, 'getPartesPendientes'])->name('partes-pendientes');
        Route::get('/alertas', [EncargadoDashboardController::class, 'getAlertas'])->name('alertas');
    });
});

// ==========================================
// RUTAS DEL PORTAL DEL TRABAJADOR
// ==========================================
use App\Http\Controllers\Trabajador\DashboardController as TrabajadorDashboardController;

Route::middleware(['auth', 'verified', 'role:Trabajador'])->prefix('trabajador')->name('trabajador.')->group(function () {
    // Dashboard Trabajador - Vista principal (Mi Portal)
    Route::get('/dashboard', [TrabajadorDashboardController::class, 'index'])->name('dashboard');

    // Mis Nóminas (descarga propia)
    Route::get('/mis-nominas', [NominaController::class, 'misNominas'])->name('mis-nominas');

    // Dashboard Trabajador - APIs para widgets AJAX
    Route::prefix('dashboard/api')->name('dashboard.api.')->group(function () {
        Route::get('/kpis', [TrabajadorDashboardController::class, 'getKpis'])->name('kpis');
        Route::get('/mis-fichajes', [TrabajadorDashboardController::class, 'getMisFichajes'])->name('mis-fichajes');
        Route::get('/mis-vacaciones', [TrabajadorDashboardController::class, 'getMisVacaciones'])->name('mis-vacaciones');
        Route::get('/mis-documentos', [TrabajadorDashboardController::class, 'getMisDocumentos'])->name('mis-documentos');
        Route::post('/documentos/{documento}/confirmar-lectura', [TrabajadorDashboardController::class, 'confirmarLecturaDocumento'])->name('confirmar-lectura');
        Route::get('/mis-epis', [TrabajadorDashboardController::class, 'getMisEpis'])->name('mis-epis');
        Route::get('/mis-formaciones', [TrabajadorDashboardController::class, 'getMisFormaciones'])->name('mis-formaciones');
        Route::get('/mis-primas', [TrabajadorDashboardController::class, 'getMisPrimas'])->name('mis-primas');
        Route::get('/alertas', [TrabajadorDashboardController::class, 'getMisAlertas'])->name('alertas');
        Route::get('/produccion-diaria', [TrabajadorDashboardController::class, 'getProduccionDiaria'])->name('produccion-diaria');
    });
});

// ==========================================
// RUTAS DE TABLEROS (Organización y Tareas)
// ==========================================

// Ver tableros - Todos los roles con permiso
Route::middleware(['auth', 'verified', 'permission:ver_tableros'])->group(function () {
    Route::get('tableros', [TableroController::class, 'index'])->name('tableros.index');
    Route::get('tableros/usuarios-por-rol', [TableroController::class, 'usuariosPorRol'])->name('tableros.usuarios.por-rol');
    Route::get('tableros/usuarios-por-obra/{obra}', [TableroController::class, 'usuariosPorObra'])->name('tableros.usuarios.por-obra');
    Route::get('tableros/{tablero}', [TableroController::class, 'show'])->name('tableros.show');
    Route::get('tableros/{tablero}/miembros', [TableroController::class, 'miembros'])->name('tableros.miembros');
    Route::get('tableros/{tablero}/etiquetas', [TableroEtiquetaController::class, 'index'])->name('tableros.etiquetas.index');
    Route::get('tarjetas/{tarjeta}', [TarjetaController::class, 'show'])->name('tarjetas.show');
    Route::get('tarjetas/{tarjeta}/comentarios', [TarjetaComentarioController::class, 'index'])->name('tarjetas.comentarios.index');
    Route::get('tarjeta-adjuntos/{adjunto}/descargar', [TarjetaAdjuntoController::class, 'descargar'])->name('tarjeta-adjuntos.descargar');
});

// Crear tableros
Route::middleware(['auth', 'verified', 'permission:crear_tableros'])->group(function () {
    Route::get('tableros-crear', [TableroController::class, 'create'])->name('tableros.create');
    Route::post('tableros', [TableroController::class, 'store'])->name('tableros.store');
});

// Editar tableros + AJAX de escritura
Route::middleware(['auth', 'verified', 'permission:editar_tableros'])->group(function () {
    Route::get('tableros/{tablero}/edit', [TableroController::class, 'edit'])->name('tableros.edit');
    Route::put('tableros/{tablero}', [TableroController::class, 'update'])->name('tableros.update');
    Route::post('tableros/{tablero}/archivar', [TableroController::class, 'archivar'])->name('tableros.archivar');

    // Miembros
    Route::post('tableros/{tablero}/miembros', [TableroController::class, 'agregarMiembro'])->name('tableros.miembros.agregar');
    Route::delete('tableros/{tablero}/miembros/{user}', [TableroController::class, 'removerMiembro'])->name('tableros.miembros.remover');

    // Columnas
    Route::post('tableros/{tablero}/columnas', [TableroColumnaController::class, 'store'])->name('tableros.columnas.store');
    Route::put('tablero-columnas/{columna}', [TableroColumnaController::class, 'update'])->name('tablero-columnas.update');
    Route::delete('tablero-columnas/{columna}', [TableroColumnaController::class, 'destroy'])->name('tablero-columnas.destroy');
    Route::post('tableros/{tablero}/columnas/reordenar', [TableroColumnaController::class, 'reordenar'])->name('tableros.columnas.reordenar');

    // Etiquetas
    Route::post('tableros/{tablero}/etiquetas', [TableroEtiquetaController::class, 'store'])->name('tableros.etiquetas.store');
    Route::put('tablero-etiquetas/{etiqueta}', [TableroEtiquetaController::class, 'update'])->name('tablero-etiquetas.update');
    Route::delete('tablero-etiquetas/{etiqueta}', [TableroEtiquetaController::class, 'destroy'])->name('tablero-etiquetas.destroy');

    // Tarjetas CRUD
    Route::post('tarjetas', [TarjetaController::class, 'store'])->name('tarjetas.store');
    Route::put('tarjetas/{tarjeta}', [TarjetaController::class, 'update'])->name('tarjetas.update');
    Route::post('tarjetas/{tarjeta}/mover', [TarjetaController::class, 'mover'])->name('tarjetas.mover');
    Route::post('tarjetas/reordenar', [TarjetaController::class, 'reordenar'])->name('tarjetas.reordenar');
    Route::post('tarjetas/{tarjeta}/archivar', [TarjetaController::class, 'archivar'])->name('tarjetas.archivar');

    // Asignaciones
    Route::post('tarjetas/{tarjeta}/usuarios', [TarjetaController::class, 'asignarUsuario'])->name('tarjetas.usuarios.asignar');
    Route::delete('tarjetas/{tarjeta}/usuarios/{user}', [TarjetaController::class, 'desasignarUsuario'])->name('tarjetas.usuarios.desasignar');
    Route::post('tarjetas/{tarjeta}/etiquetas', [TarjetaController::class, 'toggleEtiqueta'])->name('tarjetas.etiquetas.toggle');

    // Checklists
    Route::post('tarjetas/{tarjeta}/checklists', [TarjetaChecklistController::class, 'store'])->name('tarjetas.checklists.store');
    Route::put('tarjeta-checklists/{checklist}', [TarjetaChecklistController::class, 'update'])->name('tarjeta-checklists.update');
    Route::delete('tarjeta-checklists/{checklist}', [TarjetaChecklistController::class, 'destroy'])->name('tarjeta-checklists.destroy');
    Route::post('tarjeta-checklists/{checklist}/items', [TarjetaChecklistController::class, 'storeItem'])->name('tarjeta-checklists.items.store');
    Route::post('tarjeta-checklist-items/{item}/toggle', [TarjetaChecklistController::class, 'toggleItem'])->name('tarjeta-checklist-items.toggle');
    Route::delete('tarjeta-checklist-items/{item}', [TarjetaChecklistController::class, 'destroyItem'])->name('tarjeta-checklist-items.destroy');

    // Adjuntos
    Route::post('tarjetas/{tarjeta}/adjuntos', [TarjetaAdjuntoController::class, 'store'])->name('tarjetas.adjuntos.store');
    Route::delete('tarjeta-adjuntos/{adjunto}', [TarjetaAdjuntoController::class, 'destroy'])->name('tarjeta-adjuntos.destroy');
});

// Eliminar tableros (solo permisos de eliminacion)
Route::middleware(['auth', 'verified', 'permission:eliminar_tableros'])->group(function () {
    Route::delete('tableros/{tablero}', [TableroController::class, 'destroy'])->name('tableros.destroy');
    Route::delete('tarjetas/{tarjeta}', [TarjetaController::class, 'destroy'])->name('tarjetas.destroy');
});

// Comentar tarjetas (permiso separado para Trabajador)
Route::middleware(['auth', 'verified', 'permission:comentar_tarjetas'])->group(function () {
    Route::post('tarjetas/{tarjeta}/comentarios', [TarjetaComentarioController::class, 'store'])->name('tarjetas.comentarios.store');
    Route::delete('tarjeta-comentarios/{comentario}', [TarjetaComentarioController::class, 'destroy'])->name('tarjeta-comentarios.destroy');
});

require __DIR__.'/auth.php';
