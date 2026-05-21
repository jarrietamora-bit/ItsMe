package com.itsme.app.utils
import android.content.Context
import android.net.Uri
import androidx.core.content.FileProvider
import java.io.File
import java.text.SimpleDateFormat
import java.util.*

object ImageUtils {
    fun createImageFile(context: Context): File {
        val ts = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
        return File(File(context.filesDir, "images").apply { mkdirs() }, "IMG_$ts.jpg")
    }

    fun getUri(context: Context, file: File): Uri =
        FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)

    fun copyUri(context: Context, uri: Uri): String? = try {
        val ts = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
        val dest = File(File(context.filesDir, "images").apply { mkdirs() }, "IMG_$ts.jpg")
        context.contentResolver.openInputStream(uri)?.use { it.copyTo(dest.outputStream()) }
        dest.absolutePath
    } catch (e: Exception) { null }
}
