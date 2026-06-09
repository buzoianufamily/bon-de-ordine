package ro.bondeordine.launcher

import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbDeviceConnection
import android.hardware.usb.UsbEndpoint
import android.hardware.usb.UsbInterface
import android.hardware.usb.UsbManager
import android.os.Build

/**
 * Trimite octeti ESC/POS RAW la o imprimanta termica USB (ex: Bixolon BK3-31ZC/BEG).
 * Octetii bonului sunt construiti pe server (app/core/printer.php -> build_ticket_escpos),
 * deci aici doar ii transmitem pe USB. Standard ESC/POS, fara SDK de producator.
 */
object UsbEscPosPrinter {

    private const val ACTION_PERM = "ro.bondeordine.launcher.USB_PERM"
    private const val BIXOLON_VENDOR = 0x1504 // BIXOLON Co., Ltd.

    private fun manager(ctx: Context) = ctx.getSystemService(Context.USB_SERVICE) as UsbManager

    /** Gaseste cea mai potrivita imprimanta USB conectata. */
    fun findPrinter(ctx: Context): UsbDevice? {
        val devices = manager(ctx).deviceList.values
        // 1) dispozitiv cu interfata de tip "Printer" (clasa USB 7)
        for (d in devices) for (i in 0 until d.interfaceCount)
            if (d.getInterface(i).interfaceClass == UsbConstants.USB_CLASS_PRINTER) return d
        // 2) dispozitiv Bixolon dupa vendor id
        for (d in devices) if (d.vendorId == BIXOLON_VENDOR) return d
        // 3) primul dispozitiv care are un endpoint bulk OUT
        for (d in devices) for (i in 0 until d.interfaceCount) {
            val intf = d.getInterface(i)
            for (e in 0 until intf.endpointCount) {
                val ep = intf.getEndpoint(e)
                if (ep.direction == UsbConstants.USB_DIR_OUT && ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK) return d
            }
        }
        return null
    }

    /** Cere permisiunea de acces la imprimanta (o singura data; utilizatorul poate bifa "mereu"). */
    fun ensurePermission(ctx: Context) {
        val m = manager(ctx)
        val d = findPrinter(ctx) ?: return
        if (m.hasPermission(d)) return
        val flags = if (Build.VERSION.SDK_INT >= 31) PendingIntent.FLAG_IMMUTABLE else 0
        val pi = PendingIntent.getBroadcast(
            ctx, 0, Intent(ACTION_PERM).setPackage(ctx.packageName), flags
        )
        val receiver = object : BroadcastReceiver() {
            override fun onReceive(c: Context, i: Intent) { try { c.unregisterReceiver(this) } catch (_: Exception) {} }
        }
        val filter = IntentFilter(ACTION_PERM)
        if (Build.VERSION.SDK_INT >= 33)
            ctx.registerReceiver(receiver, filter, Context.RECEIVER_NOT_EXPORTED)
        else
            @Suppress("UnspecifiedRegisterReceiverFlag") ctx.registerReceiver(receiver, filter)
        m.requestPermission(d, pi)
    }

    /** Trimite octetii la imprimanta. Returneaza true daca a reusit. A se rula pe un thread separat. */
    fun print(ctx: Context, bytes: ByteArray): Boolean {
        val m = manager(ctx)
        val d = findPrinter(ctx) ?: return false
        if (!m.hasPermission(d)) { ensurePermission(ctx); return false }

        var intf: UsbInterface? = null
        var out: UsbEndpoint? = null
        loop@ for (i in 0 until d.interfaceCount) {
            val cand = d.getInterface(i)
            for (e in 0 until cand.endpointCount) {
                val ep = cand.getEndpoint(e)
                if (ep.direction == UsbConstants.USB_DIR_OUT && ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK) {
                    intf = cand; out = ep; break@loop
                }
            }
        }
        if (intf == null || out == null) return false

        val conn: UsbDeviceConnection = m.openDevice(d) ?: return false
        try {
            if (!conn.claimInterface(intf, true)) return false
            var offset = 0
            val chunk = 16384
            while (offset < bytes.size) {
                val len = minOf(chunk, bytes.size - offset)
                val part = bytes.copyOfRange(offset, offset + len)
                val sent = conn.bulkTransfer(out, part, part.size, 5000)
                if (sent < 0) return false
                offset += len
            }
            return true
        } finally {
            try { conn.releaseInterface(intf) } catch (_: Exception) {}
            conn.close()
        }
    }

    /** Bon de test (init + text + taiere). */
    fun testPrint(ctx: Context): Boolean {
        val out = ArrayList<Byte>()
        out.addAll(listOf(0x1B.toByte(), 0x40.toByte()))           // ESC @  (init)
        out.addAll("Bon de ordine - test imprimanta\n".toByteArray(Charsets.US_ASCII).toList())
        out.addAll("Bixolon ESC/POS USB\n\n\n".toByteArray(Charsets.US_ASCII).toList())
        out.addAll(listOf(0x1D.toByte(), 0x56.toByte(), 0x00.toByte())) // GS V 0 (full cut)
        return print(ctx, out.toByteArray())
    }

    /** Stare imprimanta ca JSON (folosit de pagina web prin getStatus()). */
    fun status(ctx: Context): String {
        val d = findPrinter(ctx) ?: return "{\"ready\":false,\"reason\":\"no_device\"}"
        val has = manager(ctx).hasPermission(d)
        return "{\"ready\":$has,\"vendorId\":${d.vendorId},\"productId\":${d.productId}}"
    }
}
