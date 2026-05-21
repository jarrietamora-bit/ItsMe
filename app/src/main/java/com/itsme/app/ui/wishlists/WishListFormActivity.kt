package com.itsme.app.ui.wishlists
import android.os.Bundle
import android.widget.*
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import com.itsme.app.data.entities.Client
import com.itsme.app.data.entities.WishListHeader
import com.itsme.app.databinding.ActivityWishListFormBinding
import com.itsme.app.viewmodel.ClientViewModel
import com.itsme.app.viewmodel.WishListViewModel

class WishListFormActivity : AppCompatActivity() {
    private lateinit var b: ActivityWishListFormBinding
    private val vm: WishListViewModel by viewModels()
    private val cvm: ClientViewModel by viewModels()
    private var hid = -1; private var tid = 0; private var selCid = -1
    private var clients = listOf<Client>()

    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        b = ActivityWishListFormBinding.inflate(layoutInflater); setContentView(b.root)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        hid = intent.getIntExtra("hid", -1); tid = intent.getIntExtra("tid", 0); selCid = intent.getIntExtra("cid", -1)
        if (hid != -1) { b.etListName.setText(intent.getStringExtra("name")); supportActionBar?.title = "Editar Lista" }
        else supportActionBar?.title = "Nueva Lista de Deseos"
        cvm.all.observe(this) { list ->
            clients = list
            val adapter = ArrayAdapter(this, android.R.layout.simple_spinner_item, list.map { it.fullName })
            adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
            b.spinnerClient.adapter = adapter
            if (selCid != -1) { val idx = list.indexOfFirst { it.id == selCid }; if (idx >= 0) b.spinnerClient.setSelection(idx) }
        }
        b.btnSave.setOnClickListener { save() }
    }
    private fun save() {
        val name = b.etListName.text.toString().trim()
        if (name.isEmpty()) { b.etListName.error = "Requerido"; return }
        if (clients.isEmpty()) { Toast.makeText(this, "No hay clientes", Toast.LENGTH_SHORT).show(); return }
        val cid = clients[b.spinnerClient.selectedItemPosition].id
        val h = WishListHeader(id = if (hid != -1) hid else 0, listName = name, clientId = cid, travelDateId = tid)
        if (hid != -1) vm.updateHeader(h) else vm.insertHeader(h)
        Toast.makeText(this, "Guardado", Toast.LENGTH_SHORT).show(); finish()
    }
    override fun onSupportNavigateUp(): Boolean { finish(); return true }
}
