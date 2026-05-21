package com.itsme.app.ui.articles
import android.Manifest
import android.os.Build
import android.os.Bundle
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import com.bumptech.glide.Glide
import com.itsme.app.R
import com.itsme.app.data.entities.Article
import com.itsme.app.databinding.ActivityArticleFormBinding
import com.itsme.app.utils.ImageUtils
import com.itsme.app.viewmodel.ArticleViewModel
import java.io.File

class ArticleFormActivity : AppCompatActivity() {
    private lateinit var b: ActivityArticleFormBinding
    private val vm: ArticleViewModel by viewModels()
    private var id = -1; private var tid = 0; private var photo: String? = null
    private var camFile: File? = null

    private val gallery = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let { photo = ImageUtils.copyUri(this, it); loadPhoto() } }
    private val camera = registerForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (ok) { photo = camFile?.absolutePath; loadPhoto() } }
    private val perms = registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {}

    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        b = ActivityArticleFormBinding.inflate(layoutInflater); setContentView(b.root)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        id = intent.getIntExtra("id", -1); tid = intent.getIntExtra("tid", 0)
        if (id != -1) {
            b.etName.setText(intent.getStringExtra("name"))
            b.etStore.setText(intent.getStringExtra("store"))
            b.etCostPrice.setText(intent.getDoubleExtra("cost", 0.0).toString())
            b.etSalePrice.setText(intent.getDoubleExtra("sale", 0.0).toString())
            photo = intent.getStringExtra("photo")
            supportActionBar?.title = "Editar Artículo"
            loadPhoto()
        } else supportActionBar?.title = "Nuevo Artículo"
        b.btnPhoto.setOnClickListener { pickPhoto() }
        b.btnSave.setOnClickListener { save() }
    }
    private fun pickPhoto() {
        val p = mutableListOf(Manifest.permission.CAMERA)
        if (Build.VERSION.SDK_INT >= 33) p.add(Manifest.permission.READ_MEDIA_IMAGES)
        else p.add(Manifest.permission.READ_EXTERNAL_STORAGE)
        perms.launch(p.toTypedArray())
        AlertDialog.Builder(this).setTitle("Foto").setItems(arrayOf("Galería","Cámara")) { _, i ->
            if (i == 0) gallery.launch("image/*")
            else { camFile = ImageUtils.createImageFile(this); camera.launch(ImageUtils.getUri(this, camFile!!)) }
        }.show()
    }
    private fun loadPhoto() { photo?.let { Glide.with(this).load(File(it)).placeholder(R.drawable.ic_image).into(b.ivPhoto) } }
    private fun save() {
        val name = b.etName.text.toString().trim()
        val store = b.etStore.text.toString().trim()
        val cost = b.etCostPrice.text.toString().toDoubleOrNull()
        val sale = b.etSalePrice.text.toString().toDoubleOrNull()
        if (name.isEmpty()) { b.etName.error = "Requerido"; return }
        if (cost == null) { b.etCostPrice.error = "Número inválido"; return }
        if (sale == null) { b.etSalePrice.error = "Número inválido"; return }
        val a = Article(id = if (id != -1) id else 0, name = name, store = store, costPrice = cost, salePrice = sale, photoPath = photo, travelDateId = tid)
        if (id != -1) vm.update(a) else vm.insert(a)
        Toast.makeText(this, "Guardado", Toast.LENGTH_SHORT).show(); finish()
    }
    override fun onSupportNavigateUp(): Boolean { finish(); return true }
}
