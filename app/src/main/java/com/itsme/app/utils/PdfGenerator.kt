package com.itsme.app.utils
import android.content.Context
import android.graphics.*
import android.graphics.pdf.PdfDocument
import android.os.Environment
import com.itsme.app.data.entities.Client
import com.itsme.app.data.entities.OrderItem
import java.io.File
import java.io.FileOutputStream
import java.text.SimpleDateFormat
import java.util.*

object PdfGenerator {
    fun generate(context: Context, client: Client, items: List<OrderItem>, total: Double, travelName: String): File? {
        return try {
            val doc = PdfDocument()
            val page = doc.startPage(PdfDocument.PageInfo.Builder(595, 842, 1).create())
            val cv = page.canvas
            val bold = Paint().apply { textSize = 18f; isFakeBoldText = true; color = Color.parseColor("#1976D2") }
            val head = Paint().apply { textSize = 13f; isFakeBoldText = true; color = Color.BLACK }
            val body = Paint().apply { textSize = 12f; color = Color.DKGRAY }
            val line = Paint().apply { color = Color.LTGRAY; strokeWidth = 1f }
            val tot  = Paint().apply { textSize = 14f; isFakeBoldText = true; color = Color.parseColor("#1976D2") }
            var y = 50f
            cv.drawText("IT'S ME - Pedido", 50f, y, bold); y += 28f
            cv.drawText("Viaje: $travelName", 50f, y, body); y += 20f
            cv.drawText("Fecha: ${SimpleDateFormat("dd/MM/yyyy", Locale.getDefault()).format(Date())}", 50f, y, body); y += 25f
            cv.drawLine(50f, y, 545f, y, line); y += 18f
            cv.drawText("Cliente: ${client.fullName}", 50f, y, head); y += 18f
            cv.drawText("Tel: ${client.phone}   Email: ${client.email}", 50f, y, body); y += 25f
            cv.drawLine(50f, y, 545f, y, line); y += 18f
            cv.drawText("Artículo", 50f, y, head)
            cv.drawText("Cant", 360f, y, head)
            cv.drawText("Precio", 415f, y, head)
            cv.drawText("Total", 490f, y, head)
            y += 14f; cv.drawLine(50f, y, 545f, y, line); y += 18f
            items.forEach { item ->
                val n = if (item.articleName.length > 33) item.articleName.take(30) + "..." else item.articleName
                cv.drawText(n, 50f, y, body)
                cv.drawText("${item.quantity}", 365f, y, body)
                cv.drawText("$${String.format("%.2f", item.salePrice)}", 410f, y, body)
                cv.drawText("$${String.format("%.2f", item.salePrice * item.quantity)}", 485f, y, body)
                y += 18f; if (y > 790f) return@forEach
            }
            y += 10f; cv.drawLine(50f, y, 545f, y, line); y += 22f
            cv.drawText("TOTAL: $${String.format("%.2f", total)}", 390f, y, tot)
            doc.finishPage(page)
            val dir = File(context.getExternalFilesDir(Environment.DIRECTORY_DOCUMENTS), "ItsMe").apply { mkdirs() }
            val f = File(dir, "Pedido_${client.fullName.replace(" ","_")}_${System.currentTimeMillis()}.pdf")
            FileOutputStream(f).use { doc.writeTo(it) }
            doc.close(); f
        } catch (e: Exception) { e.printStackTrace(); null }
    }
}
