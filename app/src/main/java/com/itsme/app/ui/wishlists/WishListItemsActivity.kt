package com.itsme.app.ui.wishlists
import android.Manifest
import android.os.Build
import android.os.Bundle
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import com.bumptech.glide.Glide
import com.itsme.app.R
import com.itsme.app.data.entities.WishListItem
import com.itsme.app.databinding.ActivityWishListItemsBinding
import com.itsme.app.databinding.DialogWishListItemBinding
import com.itsme.app.utils.ImageUtils
import com.itsme.app.viewmodel.WishListViewModel
import java.io.File

class WishListItemsActivity : AppCompatActivity() {
    private lateinit var b: ActivityWishListItemsBinding
    private val vm: WishListViewModel by viewModels()
    private var hid = -1; private var itemPhoto: String? = null; private var camFile: File? = null

    private val gallery = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let { itemPhoto = ImageUtils.copyUri(this, it) } }
    private val camera = registerForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (ok) itemPhoto = camFile?.absolutePath }
    private val perms = registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {}

    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        b = ActivityWishListItemsBinding.inflate(layoutInflater); setContentView(b.root)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        hid = intent.getIntExtra("hid", -1)
        supportActionBar?.title = intent.getStringExtra("name") ?: "Items"
        val adapter = WishListItemAdapter(onEdit = { showDialog(it) }, onDelete = { item ->
            AlertDialog.Builder(this).setTitle("Eliminar").setMessage("¿Eliminar '${item.name}'?")
                .setPositiveButton("Eliminar") { _,_ -> vm.deleteItem(item) }.setNegativeButton("Cancelar",null).show()
        })
        b.rvItems.layoutManager = LinearLayoutManager(this)
        b.rvItems.adapter = adapter
        vm.items(hid).observe(this) { adapter.submitList(it) }
        b.fabAddItem.setOnClickListener { showDialog(null) }
    }
    private fun showDialog(existing: WishListItem?) {
        itemPhoto = existing?.photoPath
        val vb = DialogWishListItemBinding.inflate(layoutInflater)
        existing?.let { vb.etItemName.setText(it.name); if (!it.photoPath.isNullOrEmpty()) Glide.with(this).load(File(it.photoPath)).into(vb.ivItemPhoto) }
        vb.btnItemPhoto.setOnClickListener {
            val p = mutableListOf(Manifest.permission.CAMERA)
            if (Build.VERSION.SDK_INT >= 33) p.add(Manifest.permission.READ_MEDIA_IMAGES) else p.add(Manifest.permission.READ_EXTERNAL_STORAGE)
            perms.launch(p.toTypedArray())
            AlertDialog.Builder(this).setTitle("Foto").setItems(arrayOf("Galería","Cámara")) { _, i ->
                if (i == 0) gallery.launch("image/*")
                else { camFile = ImageUtils.createImageFile(this); camera.launch(ImageUtils.getUri(this, camFile!!)) }
            }.show()
        }
        AlertDialog.Builder(this).setTitle(if (existing == null) "Nuevo Item" else "Editar Item").setView(vb.root)
            .setPositiveButton("Guardar") { _, _ ->
                val name = vb.etItemName.text.toString().trim()
                if (name.isEmpty()) { Toast.makeText(this, "Ingrese nombre", Toast.LENGTH_SHORT).show(); return@setPositiveButton }
                val item = WishListItem(id = existing?.id ?: 0, headerId = hid, name = name, photoPath = itemPhoto)
                if (existing != null) vm.updateItem(item) else vm.insertItem(item)
            }.setNegativeButton("Cancelar", null).show()
    }
    override fun onSupportNavigateUp(): Boolean { finish(); return true }
}
