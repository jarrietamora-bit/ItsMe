package com.itsme.app.ui.clients
import android.os.Bundle
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import com.itsme.app.data.entities.Client
import com.itsme.app.databinding.ActivityClientFormBinding
import com.itsme.app.viewmodel.ClientViewModel

class ClientFormActivity : AppCompatActivity() {
    private lateinit var b: ActivityClientFormBinding
    private val vm: ClientViewModel by viewModels()
    private var id = -1

    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        b = ActivityClientFormBinding.inflate(layoutInflater); setContentView(b.root)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        id = intent.getIntExtra("id", -1)
        if (id != -1) {
            b.etFullName.setText(intent.getStringExtra("name"))
            b.etEmail.setText(intent.getStringExtra("email"))
            b.etPhone.setText(intent.getStringExtra("phone"))
            supportActionBar?.title = "Editar Cliente"
        } else supportActionBar?.title = "Nuevo Cliente"
        b.btnSave.setOnClickListener { save() }
    }
    private fun save() {
        val name = b.etFullName.text.toString().trim()
        val email = b.etEmail.text.toString().trim()
        val phone = b.etPhone.text.toString().trim()
        if (name.isEmpty()) { b.etFullName.error = "Requerido"; return }
        if (phone.isEmpty()) { b.etPhone.error = "Requerido"; return }
        val c = Client(id = if (id != -1) id else 0, fullName = name, email = email, phone = phone)
        if (id != -1) vm.update(c) else vm.insert(c)
        Toast.makeText(this, "Guardado", Toast.LENGTH_SHORT).show(); finish()
    }
    override fun onSupportNavigateUp(): Boolean { finish(); return true }
}
