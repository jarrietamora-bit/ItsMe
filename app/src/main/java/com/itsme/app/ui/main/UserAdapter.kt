package com.itsme.app.ui.main
import android.view.*
import androidx.recyclerview.widget.*
import com.itsme.app.data.entities.User
import com.itsme.app.databinding.ItemUserBinding

class UserAdapter(private val onDelete: (User) -> Unit) : ListAdapter<User, UserAdapter.VH>(object : DiffUtil.ItemCallback<User>() {
    override fun areItemsTheSame(a: User, b: User) = a.id == b.id
    override fun areContentsTheSame(a: User, b: User) = a == b
}) {
    inner class VH(val b: ItemUserBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemUserBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val u = getItem(pos)
        h.b.tvUsername.text = u.username
        h.b.tvRole.text = if (u.isAdmin) "Admin" else "Usuario"
        h.b.btnDelete.setOnClickListener { onDelete(u) }
    }
}
