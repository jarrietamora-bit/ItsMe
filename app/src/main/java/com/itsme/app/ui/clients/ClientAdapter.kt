package com.itsme.app.ui.clients
import android.content.Intent
import android.net.Uri
import android.view.*
import androidx.recyclerview.widget.*
import com.itsme.app.data.entities.Client
import com.itsme.app.databinding.ItemClientBinding

class ClientAdapter(val onEdit: (Client)->Unit, val onDelete: (Client)->Unit) :
    ListAdapter<Client, ClientAdapter.VH>(object: DiffUtil.ItemCallback<Client>() {
        override fun areItemsTheSame(a: Client, b: Client) = a.id==b.id
        override fun areContentsTheSame(a: Client, b: Client) = a==b
    }) {
    inner class VH(val b: ItemClientBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemClientBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val c = getItem(pos)
        h.b.tvClientName.text = c.fullName
        h.b.tvClientEmail.text = c.email
        h.b.tvClientPhone.text = c.phone
        h.b.btnEdit.setOnClickListener { onEdit(c) }
        h.b.btnDelete.setOnClickListener { onDelete(c) }
        h.b.btnWhatsapp.setOnClickListener {
            val phone = c.phone.replace(Regex("[^\\d+]"), "")
            h.b.root.context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$phone")))
        }
    }
}
